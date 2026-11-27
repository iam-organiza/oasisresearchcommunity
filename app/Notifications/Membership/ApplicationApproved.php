<?php

namespace App\Notifications\Membership;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Admitted: their grade, the induction fee to pay, and what comes next.
 */
class ApplicationApproved extends Notification
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
        $induction = $this->membership->user->charges()->with('payments')->where('kind', 'induction')->whereNull('cancelled_at')->latest('id')->first();
        $toPay = $induction?->balance() ?? 0;

        $mail = (new MailMessage)
            ->subject("You are admitted as {$grade}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("You are admitted to OASIS Research Community as {$grade}.");

        // Nothing to pay (waived, or no fee): straight on to the induction.
        if ($toPay === 0) {
            return $mail
                ->action('View my membership', route('membership.show'))
                ->markdown('notifications::email', [
                    'facts' => [['Grade', $grade], ['Induction fee', 'Nothing to pay']],
                    'next' => ['text' => 'Choose how you receive your induction: at the ceremony, or with your induction pack posted to you.'],
                ]);
        }

        return $mail
            ->line('Your membership becomes active, and your membership number is issued, as soon as your induction fee is paid. Then you choose how you receive your induction: at the ceremony, or with your induction pack posted to you.')
            ->action('Pay my induction fee', route('membership.account'))
            ->markdown('notifications::email', [
                'facts' => [['Grade', $grade], ['Induction fee', '₦'.number_format($toPay)]],
                'next' => ['text' => 'Pay your induction fee of ₦'.number_format($toPay)
                    .($induction->note ? ' ('.lcfirst($induction->note).')' : '')
                    .' on your fees and payments page, online or by bank transfer.'],
            ]);
    }
}
