<?php

namespace App\Notifications\Membership;

use App\Models\MembershipApplication;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * To the referee an applicant named: can they vouch for the applicant?
 * The link is signed and lasts 30 days.
 */
class RefereeRequest extends Notification
{
    public function __construct(public MembershipApplication $application) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $applicant = $this->application->user->name;

        return (new MailMessage)
            ->subject("Can you vouch for {$applicant}?")
            ->greeting("Hello {$this->application->referee_name},")
            ->line("{$applicant} has applied to join OASIS Research Community, a professional body for researchers, and named you as a referee ({$this->application->referee_relationship}).")
            ->line('Please tell us whether you can vouch for their character and their work in research. It only takes a moment.')
            ->action('Answer the request', URL::temporarySignedRoute('referee.show', now()->addDays(30), ['application' => $this->application->id]))
            ->line('If you do not know this person, you can ignore this email or say so on the page.');
    }
}
