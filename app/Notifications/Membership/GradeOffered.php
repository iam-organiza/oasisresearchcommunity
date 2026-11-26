<?php

namespace App\Notifications\Membership;

use App\Account\Fees;
use App\Membership\GradeOffer;
use App\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The grade offer: from the site on submitting, or corrected by the
 * Registrar. Says why, if it is lower than asked for, and what comes next.
 */
class GradeOffered extends Notification
{
    public function __construct(public MembershipApplication $application, public bool $corrected = false) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $offered = $this->application->offered_grade?->label();
        $applied = $this->application->grade?->label();

        $mail = (new MailMessage)
            ->subject($this->corrected ? "Your grade offer has changed: {$offered}" : "Your grade offer: {$offered}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line($this->corrected
                ? "The Registrar has looked at your application and now offers you {$offered}."
                : "Thank you for applying to OASIS Research Community. From your answers, we offer you {$offered}.");

        foreach ($this->application->offer_reasons ?? [] as $reason) {
            $mail->line($reason);
        }

        if ($offered !== $applied && ! $this->corrected) {
            $mail->line("If you believe you meet the criteria for {$applied}, you can appeal to the Registrar from your membership page.");
        }

        $mail->line(GradeOffer::needsExam($this->application)
            ? 'Next: accept the offer and pay the exam fee of ₦'.number_format(app(Fees::class)->exam()).'. That opens the prep materials and the exam.'
            : 'Next: accept the offer. The Registrar then checks your route to membership and lets you know.');

        return $mail->action($this->application->status->value === 'accepted' ? 'View my membership' : 'See my offer', route('membership.show'));
    }
}
