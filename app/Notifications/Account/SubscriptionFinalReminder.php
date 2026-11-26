<?php

namespace App\Notifications\Account;

use App\Models\Charge;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Seven days before an unpaid subscription makes the membership lapse.
 */
class SubscriptionFinalReminder extends Notification
{
    public function __construct(public Charge $charge) {}

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
        $ends = $this->charge->graceEndsOn();

        return (new MailMessage)
            ->subject('7 days left to pay your subscription')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('Your yearly subscription has ₦'.number_format($balance).' still to pay. It was due on '.$this->charge->due_on?->format('j F Y').'.')
            ->line("If it is not paid by {$ends?->format('j F Y')}, your membership lapses. Your portfolio page goes offline, anything you write stays in draft, and member benefits such as your free OASIS Journals publication stop until you pay. Your certificate stays yours.")
            ->action('Pay my subscription', route('membership.account'));
    }
}
