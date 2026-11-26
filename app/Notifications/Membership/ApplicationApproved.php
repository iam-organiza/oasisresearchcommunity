<?php

namespace App\Notifications\Membership;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Welcome: their number, grade and what happens about the induction fee.
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
        $grade = $this->membership->grade;
        $mail = (new MailMessage)
            ->subject('Welcome to OASIS Research Community')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your application is approved. You are now a {$grade->label()} of OASIS Research Community.")
            ->line("**Your membership number:** {$this->membership->number}");

        $mail = $this->membership->isActive()
            ? $mail->line('Your membership is active.')
            : $mail->line('Your membership becomes active once your induction fee of ₦'.number_format($grade->inductionFee()).' is received. The Secretariat will tell you how to pay; your fees and payments page shows what you owe and every receipt.');

        return $mail->action('View my fees and payments', route('membership.account'));
    }
}
