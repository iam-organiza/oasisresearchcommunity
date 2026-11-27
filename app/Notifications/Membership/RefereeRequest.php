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
        $relationship = $this->application->referee_relationship;

        // Not sent at present (the referee step was dropped); kept for when referees return on the profile.
        return (new MailMessage)
            ->subject("Can you vouch for {$applicant}?")
            ->greeting("Hello {$this->application->referee_name},")
            ->line("{$applicant} has applied to join OASIS Research Community, a professional body for researchers, and named you as a referee.")
            ->action('Answer the request', URL::temporarySignedRoute('referee.show', now()->addDays(30), ['application' => $this->application->id]))
            ->markdown('notifications::email', [
                'facts' => array_filter([
                    ['Applicant', $applicant],
                    $relationship ? ['How you know them', $relationship] : null,
                ]),
                'next' => ['text' => 'Tell us whether you can vouch for their character and their work in research. It only takes a moment, and you do not need an account. The link lasts 30 days. If you do not know this person, you can ignore this email or say so on the page.'],
            ]);
    }
}
