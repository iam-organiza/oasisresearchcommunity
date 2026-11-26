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
            ->line("**Receipt number:** {$this->payment->receipt_number}")
            ->line('**Amount:** ₦'.number_format($this->payment->amount))
            ->line("**For:** {$charge->label()}")
            ->line("**Paid by:** {$this->payment->method->label()}, {$this->payment->paid_on->format('j F Y')}")
            ->line($balance === 0
                ? 'This charge is now fully settled.'
                : '₦'.number_format($balance).' is still to pay on this charge.')
            ->action('View my fees and payments', route('membership.account'));
    }
}
