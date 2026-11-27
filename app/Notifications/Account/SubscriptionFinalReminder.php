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
            ->line('Your yearly subscription is still unpaid, and there are 7 days left to pay it.')
            ->action('Pay my subscription', route('membership.account'))
            ->markdown('notifications::email', [
                'facts' => [
                    ['Still to pay', '₦'.number_format($balance)],
                    ['Was due', (string) $this->charge->due_on?->format('j M Y')],
                    ['Pay by', (string) $ends?->format('j M Y')],
                ],
                'whyTitle' => 'If it is not paid by then, your membership lapses',
                'why' => [
                    'Your portfolio page goes offline.',
                    'Anything you write stays in draft.',
                    'Member benefits, such as your free OASIS Journals publication, stop until you pay.',
                ],
                'next' => ['text' => 'Pay what is left, all at once or in parts, to keep your membership active. Your certificate stays yours either way.'],
            ]);
    }
}
