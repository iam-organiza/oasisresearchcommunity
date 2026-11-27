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
        $partPaid = $balance < $this->charge->amount;

        return (new MailMessage)
            ->subject('Your yearly subscription is due')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('Your OASIS Research Community subscription is due today.')
            ->action('Pay my subscription', route('membership.account'))
            ->markdown('notifications::email', [
                'facts' => [
                    [$partPaid ? 'Still to pay' : 'To pay', '₦'.number_format($balance)],
                    ['Due', (string) $this->charge->due_on?->format('j M Y')],
                    ['Pay by', (string) $this->charge->graceEndsOn()?->format('j M Y')],
                ],
                'next' => ['text' => ($partPaid
                    ? 'Pay the rest of your ₦'.number_format($this->charge->amount).' subscription, all at once or in parts. '
                    : 'Pay it all at once or in parts. ')
                    .'After the pay-by date your membership lapses: your portfolio page goes offline and most member features stop until you pay.'],
            ]);
    }
}
