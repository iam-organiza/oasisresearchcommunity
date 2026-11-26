<?php

namespace App\Membership;

use App\Enums\UserRole;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Notifications\Membership\ApplicationReceived;
use App\Notifications\Membership\ApplicationWaiting;
use App\Notifications\Membership\RefereeRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifier;
use Throwable;

/**
 * Sends the membership emails. A mail problem is logged rather than undoing
 * the decision or submission it belongs to; the applicant always sees the
 * current state on their My membership page.
 */
final class MembershipMail
{
    public static function send(mixed $notifiables, Notification $notification): void
    {
        try {
            Notifier::send($notifiables, $notification);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Who hears about new work: the registrars, or the admins if there is no registrar yet.
     *
     * @return Collection<int, User>
     */
    public static function registrars(): Collection
    {
        $registrars = User::query()->where('role', UserRole::Registrar->value)->get();

        return $registrars->isNotEmpty()
            ? $registrars
            : User::query()->where('role', UserRole::Admin->value)->get();
    }

    /**
     * After a submission, appeal or resubmission: tell the applicant and the
     * registrar, and ask a newly named referee to confirm.
     */
    public static function submitted(MembershipApplication $application, string $kind): void
    {
        self::send($application->user, new ApplicationReceived($application, $kind));
        self::send(self::registrars(), new ApplicationWaiting($application, $kind));

        if ($application->referee_email && $application->referee_status === null) {
            try {
                Notifier::route('mail', $application->referee_email)->notify(new RefereeRequest($application));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
