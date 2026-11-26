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
            ->line('Your yearly subscription was due on '.$this->charge->due_on?->format('j F Y').' and ₦'.number_format($balance).' of it is still unpaid, so your membership ('.$this->membership->number.') has lapsed.')
            ->line('Until you pay, your portfolio page is offline, anything you write stays in draft, and member benefits are paused. Your certificate is still yours.')
            ->line('Everything comes back as soon as the subscription is paid.')
            ->action('Pay my subscription', route('membership.account'));
    }
}
