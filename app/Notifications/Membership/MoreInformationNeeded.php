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
            ->line('The Registrar has looked at your application and needs a little more from you.')
            ->action('Update my application', route('membership.show'))
            ->markdown('notifications::email', [
                'quote' => ['label' => 'From the Registrar', 'text' => (string) $this->application->registrar_message],
                'next' => ['text' => 'Update your answers or documents, then submit the application again from the Review step.'],
            ]);
    }
}
