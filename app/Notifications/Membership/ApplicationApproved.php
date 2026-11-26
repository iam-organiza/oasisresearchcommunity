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

        $mail = (new MailMessage)
            ->subject("You are admitted as {$grade}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("You are admitted to OASIS Research Community as {$grade}.");

        if ($induction !== null) {
            $mail->line('**Your induction fee:** ₦'.number_format($induction->balance()).($induction->note ? ' ('.lcfirst($induction->note).')' : '').'.');
        }

        return $mail
            ->line('Pay it on your fees and payments page, online or by bank transfer. Your membership becomes active, and your membership number is issued, as soon as it is paid.')
            ->line('Then choose how you receive your induction: at the ceremony, or with your induction pack posted to you.')
            ->action('Pay my induction fee', route('membership.account'));
    }
}
