<?php

namespace App\Notifications\Membership;

use App\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationDeclined extends Notification
{
    public function __construct(public MembershipApplication $application) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your membership application')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('Thank you for applying. Your application was not approved this time.')
            ->action('View my application', route('membership.show'))
            ->markdown('notifications::email', [
                'quote' => array_values(array_filter([
                    $this->application->decision_reason ? ['label' => 'The reason', 'text' => $this->application->decision_reason] : null,
                    $this->application->decision_improvements ? ['label' => 'What to improve or provide evidence of', 'text' => $this->application->decision_improvements] : null,
                ])),
                'next' => [
                    'title' => 'Applying again',
                    'text' => 'You can apply again once you have what the Registrar asks for.',
                ],
            ]);
    }
}
