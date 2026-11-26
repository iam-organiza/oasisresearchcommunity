<?php

namespace App\Notifications\Account;

use App\Models\Charge;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * On the day a yearly subscription falls due and is not yet paid.
 */
class SubscriptionDue extends Notification
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

        return (new MailMessage)
            ->subject('Your yearly subscription is due')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('Your OASIS Research Community subscription of ₦'.number_format($this->charge->amount)." is due today, {$this->charge->due_on?->format('j F Y')}.")
            ->line($balance < $this->charge->amount
                ? '₦'.number_format($balance).' of it is still to pay. You can pay it all at once or in parts.'
                : 'You can pay it all at once or in parts.')
            ->line("Please pay by {$this->charge->graceEndsOn()?->format('j F Y')}. After that your membership lapses: your portfolio page goes offline and most member features stop until you pay.")
            ->action('Pay my subscription', route('membership.account'));
    }
}
