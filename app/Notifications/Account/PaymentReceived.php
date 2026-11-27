<?php

namespace App\Notifications\Account;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The receipt: what was paid, towards what, and what is left on that charge.
 */
class PaymentReceived extends Notification
{
    public function __construct(public Payment $payment) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $charge = $this->payment->charge;
        $balance = $charge->balance();

        return (new MailMessage)
            ->subject("Receipt {$this->payment->receipt_number}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line('We have received your payment. Thank you.')
            ->action('Open my receipt', route('receipts.show', $this->payment))
            ->line('Every receipt is also on your [fees and payments page]('.route('membership.account').'), to print or save.')
            ->markdown('notifications::email', [
                'facts' => [
                    ['Amount', '₦'.number_format($this->payment->amount)],
                    ['For', $charge->label()],
                    ['Receipt number', $this->payment->receipt_number],
                    ['Paid by', $this->payment->method->label().', '.$this->payment->paid_on->format('j M Y')],
                ],
                'next' => $balance === 0
                    ? ['title' => 'Settled', 'text' => 'This charge is now fully settled.']
                    : ['title' => 'Still to pay', 'text' => '₦'.number_format($balance).' is still to pay on this charge.'],
            ]);
    }
}
