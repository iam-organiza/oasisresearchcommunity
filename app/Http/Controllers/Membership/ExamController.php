<?php

namespace App\Http\Controllers\Membership;

use App\Enums\Membership\ApplicationStatus;
use App\Exam\Exam;
use App\Exam\Paper;
use App\Http\Controllers\Controller;
use App\Membership\GradeOffer;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\MembershipApplication;
use App\Models\PrepMaterial;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The applicant's exam: the exam page (prep materials, attempts and
 * results), sitting an attempt, and paying the fee again.
 */
class ExamController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $application = $this->application($request->user());
        if (! $application) {
            return to_route('membership.show');
        }

        $status = Exam::status($application);
        $paid = $status['paid'];

        return Inertia::render('membership/exam', [
            'grade' => ($application->offered_grade ?? $application->grade)?->label(),
            'fee' => $status['fee'] ? ['amount' => $status['fee']->amount, 'paid' => $paid] : null,
            'attemptsOnFee' => $status['attempts_on_fee'],
            'attemptsPerFee' => Exam::ATTEMPTS_PER_FEE,
            'running' => $status['running'] ? ['id' => $status['running']->id, 'ends_at' => $status['running']->ends_at->toIso8601String()] : null,
            'canStart' => $status['can_start'],
            'waitUntil' => $status['wait_until']?->format('j M Y, g:i a'),
            'bankReady' => $status['bank_ready'],
            'needsNewFee' => $status['needs_new_fee'],
            'needsExam' => GradeOffer::needsExam($application),
            'passed' => $application->exam_status === 'passed',
            'rules' => ['questions' => Paper::QUESTIONS, 'minutes' => Paper::MINUTES, 'pass' => Paper::PASS_PERCENT],
            'attempts' => ExamAttempt::query()->where('membership_application_id', $application->id)->whereNotNull('finished_at')->latest('id')->get()
                ->map(fn (ExamAttempt $attempt) => [
                    'id' => $attempt->id,
                    'number' => $attempt->number,
                    'grade' => $attempt->grade->label(),
                    'date' => $attempt->finished_at?->format('j M Y, g:i a'),
                    'percent' => $attempt->percent,
                    'correct' => $attempt->correct,
                    'total' => $attempt->total,
                    'passed' => $attempt->passed,
                    'areas' => array_values($attempt->area_scores ?? []),
                ]),
            // Prep materials open once the exam fee is paid.
            'materials' => $paid || $application->exam_status === 'passed'
                ? PrepMaterial::query()->where('published', true)->orderByRaw('area is null desc')->orderBy('area')->orderBy('title')->get()
                    ->map(fn (PrepMaterial $material) => [
                        'id' => $material->id,
                        'title' => $material->title,
                        'area' => $material->areaLabel(),
                        'description' => $material->description,
                        'url' => $material->isFile() ? route('membership.exam.material', $material) : $material->url,
                        'is_file' => $material->isFile(),
                    ])
                : null,
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $application = $this->application($request->user());
        abort_unless($application !== null, 403);

        $attempt = Exam::start($application);

        return to_route('membership.exam.sit', $attempt);
    }

    public function sit(Request $request, ExamAttempt $attempt): Response|RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 404);

        if (! $attempt->isRunning()) {
            Exam::finish($attempt);

            return to_route('membership.exam');
        }

        return Inertia::render('membership/exam-sit', [
            'attempt' => [
                'id' => $attempt->id,
                'number' => $attempt->number,
                'grade' => $attempt->grade->label(),
                // Seconds left on the server's clock; the page counts down from it.
                'seconds_left' => max(0, (int) ceil(now()->diffInSeconds($attempt->ends_at, false))),
            ],
            'questions' => $attempt->questions()->get()->map(fn (ExamAttemptQuestion $question) => [
                'position' => $question->position,
                'scenario' => $question->scenario,
                'question' => $question->question,
                'options' => $question->options,
                'answer' => $question->answer,
            ]),
        ]);
    }

    public function answer(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 404);
        $data = $request->validate([
            'position' => ['required', 'integer', 'min:1'],
            'option' => ['required', 'integer', 'min:0'],
        ]);

        Exam::answer($attempt, (int) $data['position'], (int) $data['option']);

        return back();
    }

    public function finish(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 404);

        $attempt = Exam::finish($attempt);
        $score = rtrim(rtrim(number_format((float) $attempt->percent, 2), '0'), '.');
        Inertia::flash('toast', ['type' => $attempt->passed ? 'success' : 'info', 'message' => $attempt->passed
            ? "You passed with {$score}%."
            : "You scored {$score}%. Your results are below."]);

        return to_route('membership.exam');
    }

    public function payAgain(Request $request): RedirectResponse
    {
        $application = $this->application($request->user());
        abort_unless($application !== null, 403);

        if ($application->status === ApplicationStatus::Offered) {
            Exam::tryAgainInstead($application);
        } else {
            Exam::newFee($application);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'A new exam fee is on your fees and payments page.']);

        return to_route('membership.account');
    }

    public function material(Request $request, PrepMaterial $material): StreamedResponse
    {
        $application = $this->application($request->user());
        $allowed = $application && (Exam::status($application)['paid'] || $application->exam_status === 'passed');
        abort_unless($allowed && $material->published && $material->isFile() && Storage::disk('local')->exists((string) $material->path), 404);

        return Storage::disk('local')->download((string) $material->path, (string) $material->original_name);
    }

    /**
     * Their application while it is on the way to the exam (accepted), or
     * offered a grade on their exam score.
     */
    private function application(User $user): ?MembershipApplication
    {
        $application = $user->latestApplication()->first();

        return $application && in_array($application->status, [ApplicationStatus::Accepted, ApplicationStatus::Offered], true)
            ? $application
            : null;
    }
}
