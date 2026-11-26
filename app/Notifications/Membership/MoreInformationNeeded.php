<?php

namespace App\Notifications\Membership;

use App\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MoreInformationNeeded extends Notification
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
            ->subject('We need a little more for your membership application')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('The Registrar has looked at your application and needs more from you:')
            ->line((string) $this->application->registrar_message)
            ->line('Update your answers or documents, then submit the application again from the Review step.')
            ->action('Update my application', route('membership.show'));
    }
}
