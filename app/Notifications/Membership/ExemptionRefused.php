<?php

namespace App\Notifications\Membership;

use App\Account\Fees;
use App\Enums\Membership\ApplicationStatus;
use App\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExemptionRefused extends Notification
{
    public function __construct(public string $note, public ?MembershipApplication $application = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $fee = '₦'.number_format(app(Fees::class)->exam());
        // The exam fee is charged once they have accepted their offer; before that, accepting comes first.
        $accepted = $this->application?->status === ApplicationStatus::Accepted;

        return (new MailMessage)
            ->subject('Your request to be exempted from the exam')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('The Registrar has looked at your request to be exempted from the membership exam and could not grant it. Your application continues with the exam route.')
            ->action('View my application', route('membership.show'))
            ->markdown('notifications::email', [
                'quote' => ['label' => 'The Registrar\'s note', 'text' => $this->note],
                'next' => ['text' => $accepted
                    ? "Pay the exam fee of {$fee}. That opens the prep materials and the exam."
                    : "Accept your grade offer and pay the exam fee of {$fee}. That opens the prep materials and the exam."],
            ]);
    }
}
