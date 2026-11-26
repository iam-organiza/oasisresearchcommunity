<?php

namespace App\Membership;

use App\Account\Account;
use App\Enums\Membership\ApplicationStatus;
use App\Enums\Membership\Grade;
use App\Models\Membership;
use App\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Puts an approved applicant on the member register with a number and adds
 * their induction fee to their account (waived if the Registrar waived it
 * during the review). Used when the registrar approves, and when an
 * applicant accepts the grade the registrar recommended.
 */
final class ApproveApplication
{
    public static function handle(MembershipApplication $application, Grade $grade, ?User $actor, string $event = 'approved', ?string $note = null): Membership
    {
        return DB::transaction(function () use ($application, $grade, $actor, $event, $note): Membership {
            $application->status = ApplicationStatus::Approved;
            $application->decided_grade = $grade;
            $application->save();
            $application->log($event, $actor, $grade, $note);

            $membership = Membership::query()->create([
                'user_id' => $application->user_id,
                'membership_application_id' => $application->id,
                'number' => Membership::nextNumber($grade, (int) now()->year),
                'grade' => $grade,
                'state_id' => $application->state_id,
                'joined_on' => now()->toDateString(),
                'recorded_by' => $actor?->id,
            ]);
            Account::addInductionFee($membership, $actor, waived: $application->fee_waived);

            return $membership->refresh();
        });
    }
}
