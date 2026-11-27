<?php

namespace App\Notifications\Account;

use App\Models\OnlinePayment;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To the Registrar: Paystack took a member's money but the site could not
 * apply it, so it needs a refund or a payment recorded by hand.
 */
class OnlinePaymentNeedsAttention extends Notification
{
    public function __construct(public OnlinePayment $attempt, public string $why) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $attempt = $this->attempt->loadMissing(['user', 'charge']);

        return (new MailMessage)
            ->subject('An online payment needs your attention')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("{$attempt->user->name} paid on Paystack, but the website could not record the payment.")
            ->action('Open their fees and payments', route('admin.accounts.show', $attempt->user_id))
            ->markdown('notifications::email', [
                'facts' => [
                    ['Member', $attempt->user->name],
                    ['Paid', $attempt->money($attempt->total_minor)],
                    ['Towards', $attempt->charge->label()],
                    ['Paystack reference', $attempt->reference],
                ],
                'quote' => ['label' => 'What went wrong', 'text' => $this->why],
                'next' => ['text' => 'Check the payment in the Paystack dashboard, then either record it on their account by hand or refund it from Paystack.'],
            ]);
    }
}
