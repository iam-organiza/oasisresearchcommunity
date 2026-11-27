<?php

namespace App\Notifications\Account;

use App\Models\Charge;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The 30 days' grace ran out with the subscription unpaid.
 */
class MembershipLapsed extends Notification
{
    public function __construct(public Membership $membership, public Charge $charge) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $balance = $this->charge->loadMissing('payments')->balance();

        return (new MailMessage)
            ->subject('Your membership has lapsed')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('Your yearly subscription is still unpaid, so your membership has lapsed.')
            ->action('Pay my subscription', route('membership.account'))
            ->markdown('notifications::email', [
                'facts' => [
                    ['Membership number', (string) $this->membership->number],
                    ['Still to pay', '₦'.number_format($balance)],
                    ['Was due', (string) $this->charge->due_on?->format('j M Y')],
                ],
                'whyTitle' => 'Until you pay',
                'why' => [
                    'Your portfolio page is offline.',
                    'Anything you write stays in draft.',
                    'Member benefits are paused.',
                ],
                'next' => ['text' => 'Pay what is left, all at once or in parts. Everything comes back as soon as the subscription is paid. Your certificate is still yours.'],
            ]);
    }
}
