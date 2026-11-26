<?php

namespace App\Membership;

use App\Account\Account;
use App\Account\Fees;
use App\Enums\Account\ChargeKind;
use App\Enums\Membership\ApplicationStatus;
use App\Enums\Membership\Grade;
use App\Enums\Membership\Route;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Notifications\Membership\ApplicationWaiting;
use App\Notifications\Membership\GradeOffered;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The grade offer (OASIS, 2026-11-27). The site makes it when an
 * application is submitted; the Registrar can correct it; the applicant
 * accepts it (and, for the exam route, owes the exam fee) or appeals.
 */
final class GradeOffer
{
    /**
     * Ranks a submitted application and offers it a grade. The Registrar
     * hears only when there is something for a person to look at.
     */
    public static function make(MembershipApplication $application, ?User $actor, string $kind = 'submitted'): void
    {
        $assessment = GradeRanking::assess($application->load('publications'));

        DB::transaction(function () use ($application, $assessment, $actor) {
            $application->forceFill([
                'status' => ApplicationStatus::Offered,
                'offered_grade' => $assessment['offer'],
                'qualifying_grade' => $assessment['qualifies'],
                'offer_reasons' => $assessment['reasons'],
                'flags' => $assessment['flags'],
                'offered_at' => now(),
                'reviewed_at' => null,
            ])->save();
            $application->log('offered', $actor, $assessment['offer'], implode("\n", $assessment['reasons']) ?: null);
        });

        MembershipMail::send($application->user, new GradeOffered($application, corrected: false));
        if ($assessment['flags'] !== []) {
            MembershipMail::send(MembershipMail::registrars(), new ApplicationWaiting($application, $kind));
        }
    }

    /**
     * The Registrar changes the offer, with a reason the applicant sees.
     * An applicant who had already accepted keeps their place; anyone
     * else is asked to accept the new offer.
     */
    public static function correct(MembershipApplication $application, Grade $grade, string $reason, User $registrar): void
    {
        DB::transaction(function () use ($application, $grade, $reason, $registrar) {
            $application->forceFill([
                'status' => $application->status === ApplicationStatus::Accepted ? ApplicationStatus::Accepted : ApplicationStatus::Offered,
                'offered_grade' => $grade,
                'offer_reasons' => [$reason],
                'offered_at' => now(),
                'reviewed_at' => now(),
                'decided_by' => $registrar->id,
                'decided_at' => now(),
            ])->save();
            $application->log('offer_corrected', $registrar, $grade, $reason);
        });

        MembershipMail::send($application->user, new GradeOffered($application, corrected: true));
    }

    /**
     * The applicant takes the offer. On the exam route (still to take it)
     * the exam fee goes on their account; the exam opens once it is paid.
     */
    public static function accept(MembershipApplication $application, User $applicant): void
    {
        if ($application->status !== ApplicationStatus::Offered || $application->offered_grade === null) {
            throw ValidationException::withMessages(['offer' => 'There is no offer to accept.']);
        }

        DB::transaction(function () use ($application, $applicant) {
            $application->forceFill(['status' => ApplicationStatus::Accepted, 'accepted_at' => now()])->save();
            $application->log('offer_accepted', $applicant, $application->offered_grade);

            self::chargeExamFee($application);
        });

        // A grade earned on the exam score needs no further check: admitted at its full induction fee.
        if ($application->exam_status === 'band' && $application->offered_grade !== null) {
            ApproveApplication::handle($application, $application->offered_grade, $applicant, 'approved', 'Accepted the grade earned on the exam');
        }
    }

    /**
     * Once someone on the exam route has accepted, the exam fee goes on
     * their account (once). Also used when an exemption is refused.
     */
    public static function chargeExamFee(MembershipApplication $application): void
    {
        $applicant = $application->user;
        if ($application->status === ApplicationStatus::Accepted && self::needsExam($application) && ! self::examFeeOwedOrPaid($applicant)) {
            Account::addCharge($applicant, ChargeKind::Exam, app(Fees::class)->exam(), null);
        }
    }

    /**
     * The exam route, not yet passed: they will sit the website's exam.
     */
    public static function needsExam(MembershipApplication $application): bool
    {
        // 'band': offered a lower grade on their exam score, so no further exam for it.
        return $application->route === Route::Exam && ! in_array($application->exam_status, ['passed', 'band'], true);
    }

    private static function examFeeOwedOrPaid(User $applicant): bool
    {
        return $applicant->charges()->where('kind', ChargeKind::Exam->value)->whereNull('cancelled_at')->exists();
    }
}
