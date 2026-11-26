<?php

namespace App\Membership;

use App\Enums\UserRole;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Notifications\Membership\ApplicationReceived;
use App\Notifications\Membership\ApplicationWaiting;
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
     * After an appeal: tell the applicant it arrived and the Registrar it is waiting.
     * (Submissions get the grade offer instead: GradeOffer::make.)
     */
    public static function appealed(MembershipApplication $application): void
    {
        self::send($application->user, new ApplicationReceived($application, 'appealed'));
        self::send(self::registrars(), new ApplicationWaiting($application, 'appealed'));
    }
}
