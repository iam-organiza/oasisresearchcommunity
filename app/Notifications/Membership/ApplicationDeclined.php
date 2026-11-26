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
            ->line('**The reason:** '.$this->application->decision_reason)
            ->line('**What to improve or provide evidence of:** '.$this->application->decision_improvements)
            ->line('You can apply again once you have what the Registrar asks for.')
            ->action('View my application', route('membership.show'));
    }
}
