<?php

namespace App\Notifications\Membership;

use App\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GradeRecommended extends Notification
{
    public function __construct(public MembershipApplication $application) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $applied = $this->application->grade?->label();
        $recommended = $this->application->decided_grade?->label();

        return (new MailMessage)
            ->subject("The Registrar recommends {$recommended}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("You applied to join as {$applied}. The Registrar recommends that you join as {$recommended}.")
            ->line('**The reason:** '.$this->application->decision_reason)
            ->line('**What to improve or provide evidence of:** '.$this->application->decision_improvements)
            ->line("You can join now as {$recommended} and ask to be upgraded when you have the evidence, or appeal for {$applied} with your reasons and more evidence.")
            ->action('Accept or appeal', route('membership.show'));
    }
}
