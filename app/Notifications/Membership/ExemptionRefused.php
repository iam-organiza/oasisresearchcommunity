<?php

namespace App\Notifications\Membership;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExemptionRefused extends Notification
{
    public function __construct(public string $note) {}

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
            ->subject('Your request to be exempted from the exam')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('The Registrar has looked at your request to be exempted from the membership exam and could not grant it.')
            ->line('**The Registrar\'s note:** '.$this->note)
            ->line('Your application continues with the exam route. We will let you know when the online exam opens.')
            ->action('View my application', route('membership.show'));
    }
}
