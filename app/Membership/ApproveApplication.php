<?php

namespace App\Membership;

use App\Account\Account;
use App\Account\Fees;
use App\Enums\Membership\ApplicationStatus;
use App\Enums\Membership\Grade;
use App\Models\Membership;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Notifications\Membership\ApplicationApproved;
use Illuminate\Support\Facades\DB;

/**
 * Admits an applicant: puts them on the register at their grade and adds
 * their induction fee to their account. Happens on its own when they pass
 * the exam or accept a grade earned on their exam score, and by the
 * Registrar for routes with no exam.
 *
 * Those who pass the exam at their grade have the exam fee taken off the
 * induction fee; those who take a lower grade after failing pay its full
 * fee (OASIS, 2026-11-26/27). A waiver given during the review comes too.
 *
 * The membership number is issued once the induction fee is settled and
 * the account goes live (Account::refreshMembership), not here.
 */
final class ApproveApplication
{
    public static function handle(MembershipApplication $application, Grade $grade, ?User $actor, string $event = 'approved', ?string $note = null, bool $lessExamFee = false): Membership
    {
        $membership = DB::transaction(function () use ($application, $grade, $actor, $event, $note, $lessExamFee): Membership {
            $application->status = ApplicationStatus::Approved;
            $application->decided_grade = $grade;
            $application->save();
            $application->log($event, $actor, $grade, $note);

            $membership = Membership::query()->create([
                'user_id' => $application->user_id,
                'membership_application_id' => $application->id,
                'grade' => $grade,
                'grade_since' => now()->toDateString(),
                'state_id' => $application->state_id,
                'joined_on' => now()->toDateString(),
                'recorded_by' => $actor?->id,
            ]);

            $examFee = $lessExamFee ? app(Fees::class)->exam() : 0;
            $charge = Account::addInductionFee(
                $membership,
                $actor,
                waived: (bool) $application->fee_waived,
                amount: max(0, $grade->inductionFee() - $examFee),
            );
            if ($examFee > 0) {
                $charge->forceFill(['note' => 'Less the ₦'.number_format($examFee).' exam fee'])->save();
            }

            return $membership->refresh();
        });

        // A waived fee makes them active (and numbered) at once; the welcome email has gone with the number.
        if (! $membership->isActive()) {
            MembershipMail::send($application->user, new ApplicationApproved($membership));
        }

        return $membership;
    }
}
