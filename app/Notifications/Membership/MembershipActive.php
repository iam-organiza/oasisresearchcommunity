<?php

namespace App\Notifications\Membership;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Welcome: the induction fee is settled, the account is live and the
 * membership number is issued.
 */
class MembershipActive extends Notification
{
    public function __construct(public Membership $membership) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $grade = $this->membership->grade->label();

        return (new MailMessage)
            ->subject('Welcome to OASIS Research Community')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your induction fee is settled and your membership is active. Welcome, {$grade}.")
            ->line("**Your membership number:** {$this->membership->number}")
            ->line('Complete your profile while you wait for your induction, and choose on your membership page whether you will attend the ceremony or have your induction pack posted.')
            ->action('View my membership', route('membership.show'));
    }
}
