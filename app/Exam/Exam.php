<?php

namespace App\Exam;

use App\Account\Account;
use App\Account\Fees;
use App\Enums\Account\ChargeKind;
use App\Enums\Membership\ApplicationStatus;
use App\Enums\Membership\Grade;
use App\Membership\ApplicationSteps;
use App\Membership\ApproveApplication;
use App\Membership\GradeOffer;
use App\Membership\MembershipMail;
use App\Models\Charge;
use App\Models\ExamAttempt;
use App\Models\MembershipApplication;
use App\Notifications\Membership\ExamResult;
use App\Notifications\Membership\GradeOffered;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Taking the membership exam (OASIS, 2026-11-26/27):
 * - it opens once the exam fee is paid; each fee gives three attempts, one
 *   straight after another;
 * - 50 questions in 60 minutes on the server's clock, auto-marked, 60% to
 *   pass;
 * - after three failed attempts, the best score decides: a lower grade can
 *   be offered (Fellow: 50-59% Member, 40-49% Graduate; Member: 50-59%
 *   Graduate), or they pay the fee again and try after 24 hours.
 */
final class Exam
{
    public const ATTEMPTS_PER_FEE = 3;

    public const WAIT_HOURS = 24;

    /**
     * Where an applicant stands with the exam, for their exam page.
     *
     * @return array{fee: Charge|null, paid: bool, attempts_on_fee: int, running: ExamAttempt|null, can_start: bool, wait_until: CarbonImmutable|null, bank_ready: bool, needs_new_fee: bool}
     */
    public static function status(MembershipApplication $application): array
    {
        self::closeExpired($application);

        $fee = self::currentFee($application);
        $paid = $fee !== null && $fee->isSettled();
        $attemptsOnFee = $fee ? ExamAttempt::query()->where('charge_id', $fee->id)->count() : 0;
        $running = ExamAttempt::query()->where('membership_application_id', $application->id)->whereNull('finished_at')->latest('id')->first();
        $grade = $application->offered_grade ?? $application->grade ?? Grade::Student;
        $bankReady = Paper::canSet($grade);
        $waitUntil = self::waitUntil($application, $fee, $attemptsOnFee);

        $open = $application->status === ApplicationStatus::Accepted && GradeOffer::needsExam($application);

        return [
            'fee' => $fee,
            'paid' => $paid,
            'attempts_on_fee' => $attemptsOnFee,
            'running' => $running,
            'can_start' => $open && $paid && $running === null && $attemptsOnFee < self::ATTEMPTS_PER_FEE && $bankReady
                && ($waitUntil === null || $waitUntil->isPast()),
            'wait_until' => $waitUntil?->isFuture() ? $waitUntil : null,
            'bank_ready' => $bankReady,
            'needs_new_fee' => $open && $paid && $running === null && $attemptsOnFee >= self::ATTEMPTS_PER_FEE,
        ];
    }

    public static function start(MembershipApplication $application): ExamAttempt
    {
        $status = self::status($application);
        if (! $status['can_start'] || $status['fee'] === null) {
            throw ValidationException::withMessages(['exam' => 'The exam is not open to you right now.']);
        }

        $grade = $application->offered_grade ?? $application->grade ?? Grade::Student;

        return DB::transaction(function () use ($application, $status, $grade): ExamAttempt {
            $questions = Paper::set($application, $grade);
            $attempt = ExamAttempt::query()->create([
                'user_id' => $application->user_id,
                'membership_application_id' => $application->id,
                'charge_id' => $status['fee']->id,
                'number' => $status['attempts_on_fee'] + 1,
                'grade' => $grade,
                'started_at' => now(),
                'ends_at' => now()->addMinutes(Paper::MINUTES),
                'total' => count($questions),
            ]);
            foreach ($questions as $question) {
                $attempt->questions()->create($question);
            }
            $application->log('exam_started', $application->user, $grade, "Attempt {$attempt->number} of ".self::ATTEMPTS_PER_FEE);

            return $attempt;
        });
    }

    /**
     * Saves one answer while the attempt is open.
     */
    public static function answer(ExamAttempt $attempt, int $position, int $option): void
    {
        if (! $attempt->isRunning()) {
            self::finish($attempt);

            throw ValidationException::withMessages(['exam' => 'Time is up. Your answers so far have been marked.']);
        }

        $question = $attempt->questions()->where('position', $position)->firstOrFail();
        if ($option < 0 || $option >= count($question->options)) {
            throw ValidationException::withMessages(['answer' => 'Choose one of the options.']);
        }
        $question->forceFill(['answer' => $option, 'answered_at' => now()])->save();
    }

    /**
     * Marks an attempt (on finishing, or when its time runs out) and acts on
     * the result. Safe to call more than once.
     */
    public static function finish(ExamAttempt $attempt): ExamAttempt
    {
        $attempt = DB::transaction(function () use ($attempt): ExamAttempt {
            $attempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($attempt->isFinished()) {
                return $attempt;
            }

            $questions = $attempt->questions()->get();
            $correct = $questions->filter(fn ($question) => $question->answer === $question->correct_option)->count();
            $percent = $attempt->total > 0 ? round($correct / $attempt->total * 100, 2) : 0.0;
            $areas = $questions->groupBy('area')->map(fn ($items, string $area) => [
                'label' => ApplicationSteps::AREAS[$area]['label'] ?? $area,
                'correct' => $items->filter(fn ($question) => $question->answer === $question->correct_option)->count(),
                'total' => $items->count(),
            ])->sortBy('label')->all();

            $attempt->forceFill([
                'finished_at' => now()->lt($attempt->ends_at) ? now() : $attempt->ends_at,
                'correct' => $correct,
                'percent' => $percent,
                'passed' => $percent >= Paper::PASS_PERCENT,
                'area_scores' => $areas,
            ])->save();

            return $attempt;
        });

        if ($attempt->wasChanged('finished_at')) {
            self::afterAttempt($attempt);
        }

        return $attempt;
    }

    /**
     * Another exam fee, after three attempts on the last one.
     */
    public static function newFee(MembershipApplication $application): Charge
    {
        if (! self::status($application)['needs_new_fee']) {
            throw ValidationException::withMessages(['exam' => 'You still have attempts left on your exam fee.']);
        }

        $charge = Account::addCharge($application->user, ChargeKind::Exam, app(Fees::class)->exam(), null);
        $application->log('exam_fee_again', $application->user);

        return $charge;
    }

    /**
     * Offered a lower grade after three attempts, they choose to pay the
     * exam fee again and keep trying for the grade they sat for (after 24
     * hours) instead.
     */
    public static function tryAgainInstead(MembershipApplication $application): Charge
    {
        $last = ExamAttempt::query()->where('membership_application_id', $application->id)->whereNotNull('finished_at')->latest('id')->first();
        if ($application->status !== ApplicationStatus::Offered || $application->exam_status !== 'band' || $last === null) {
            throw ValidationException::withMessages(['exam' => 'There is no exam to try again.']);
        }

        return DB::transaction(function () use ($application, $last): Charge {
            $application->forceFill([
                'status' => ApplicationStatus::Accepted,
                'offered_grade' => $last->grade,
                'exam_status' => 'will_take',
                'offer_reasons' => [],
            ])->save();
            $application->log('exam_fee_again', $application->user, $last->grade);

            return Account::addCharge($application->user, ChargeKind::Exam, app(Fees::class)->exam(), null);
        });
    }

    /**
     * The grade the best score of three failed attempts earns, if any:
     * Fellow 50-59% Member, 40-49% Graduate; Member 50-59% Graduate.
     */
    public static function band(Grade $satFor, float $best): ?Grade
    {
        return match ($satFor) {
            Grade::Fellow => $best >= 50 ? Grade::Member : ($best >= 40 ? Grade::Graduate : null),
            Grade::Member => $best >= 50 ? Grade::Graduate : null,
            default => null,
        };
    }

    /**
     * Finishes any attempts of this application whose time is up.
     */
    public static function closeExpired(?MembershipApplication $application = null): int
    {
        $expired = ExamAttempt::query()
            ->whereNull('finished_at')
            ->where('ends_at', '<=', now())
            ->when($application, fn ($query) => $query->where('membership_application_id', $application?->id))
            ->get();
        $expired->each(fn (ExamAttempt $attempt) => self::finish($attempt));

        return $expired->count();
    }

    private static function afterAttempt(ExamAttempt $attempt): void
    {
        $application = $attempt->application()->firstOrFail();
        $user = $application->user;
        $best = (float) ExamAttempt::query()->where('membership_application_id', $application->id)->whereNotNull('finished_at')->max('percent');
        $application->forceFill(['exam_best_percent' => $best])->save();
        $application->log($attempt->passed ? 'exam_passed' : 'exam_failed', $user, $attempt->grade, "Attempt {$attempt->number}: {$attempt->percent}%");

        if ($attempt->passed) {
            $application->forceFill(['exam_status' => 'passed', 'exam_passed_on' => today()->toDateString()])->save();
            MembershipMail::send($user, new ExamResult($attempt, 'passed'));
            // Admitted straight away, with the exam fee taken off the induction fee (OASIS, 2026-11-27).
            ApproveApplication::handle($application, $attempt->grade, null, 'approved', "Passed the exam with {$attempt->percent}%", lessExamFee: true);

            return;
        }

        if ($attempt->number < self::ATTEMPTS_PER_FEE) {
            MembershipMail::send($user, new ExamResult($attempt, 'try_again'));

            return;
        }

        // Three attempts on this fee: the best of them decides.
        $bestOnFee = (float) ExamAttempt::query()->where('charge_id', $attempt->charge_id)->max('percent');
        $lower = self::band($attempt->grade, $bestOnFee);

        if ($lower === null) {
            MembershipMail::send($user, new ExamResult($attempt, 'pay_again'));

            return;
        }

        $application->forceFill([
            'status' => ApplicationStatus::Offered,
            'offered_grade' => $lower,
            'exam_status' => 'band',
            'offer_reasons' => [
                "Your best exam score was {$bestOnFee}%. That is below the ".Paper::PASS_PERCENT."% pass mark for {$attempt->grade->label()}, but it earns {$lower->label()}.",
                "You can join as {$lower->label()} now (its full induction fee), and move up later. Or pay the exam fee again and take the {$attempt->grade->label()} exam after ".self::WAIT_HOURS.' hours.',
            ],
            'offered_at' => now(),
        ])->save();
        $application->log('exam_band_offer', null, $lower, "Best of three: {$bestOnFee}%");
        MembershipMail::send($user, new GradeOffered($application, corrected: true));
    }

    private static function currentFee(MembershipApplication $application): ?Charge
    {
        return Charge::query()->with('payments')
            ->where('user_id', $application->user_id)
            ->where('kind', ChargeKind::Exam->value)
            ->whereNull('cancelled_at')
            ->latest('id')
            ->first();
    }

    /**
     * After the first exam fee, a new fee's first attempt waits 24 hours from the last one.
     */
    private static function waitUntil(MembershipApplication $application, ?Charge $fee, int $attemptsOnFee): ?CarbonImmutable
    {
        if ($fee === null || $attemptsOnFee > 0) {
            return null;
        }
        $last = ExamAttempt::query()->where('membership_application_id', $application->id)->whereNotNull('finished_at')->max('finished_at');

        return $last ? CarbonImmutable::parse($last)->addHours(self::WAIT_HOURS) : null;
    }
}
