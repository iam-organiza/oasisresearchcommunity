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
        $chapter = $this->membership->state?->name;

        return (new MailMessage)
            ->subject('Welcome to OASIS Research Community')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your induction fee is settled and your membership is active. Welcome, {$grade}.")
            ->action('View my membership', route('membership.show'))
            ->markdown('notifications::email', [
                // The chapter comes last, so leaving it out keeps the list in order.
                'facts' => array_filter([
                    ['Membership number', (string) $this->membership->number],
                    ['Grade', $grade],
                    $chapter ? ['Chapter', $chapter] : null,
                ]),
                'next' => ['text' => 'Choose on your membership page whether you will attend the induction ceremony or have your induction pack posted. Complete your profile while you wait.'],
            ]);
    }
}
