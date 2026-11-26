<?php

namespace App\Notifications\Membership;

use App\Enums\Membership\Route;
use App\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To the applicant: their application (or appeal, or resubmission) reached the Registrar.
 */
class ApplicationReceived extends Notification
{
    public function __construct(public MembershipApplication $application, public string $kind = 'submitted') {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $grade = $this->application->grade?->label() ?? 'member';

        $mail = (new MailMessage)
            ->subject(match ($this->kind) {
                'appealed' => 'We have your appeal',
                'resubmitted' => 'We have your updated application',
                default => 'We have your membership application',
            })
            ->greeting("Hello {$notifiable->first_name},")
            ->line(match ($this->kind) {
                'appealed' => "Your appeal to be admitted as {$grade} is with the Registrar.",
                'resubmitted' => 'Your updated application is back with the Registrar.',
                default => "Thank you for applying to join OASIS Research Community as {$grade}. Your application is with the Registrar.",
            });

        if ($this->kind === 'submitted' && $this->application->route === Route::Exemption) {
            $mail->line('The Registrar will also look at your request to be exempted from the exam.');
        }

        return $mail
            ->line('We will email you when there is a decision, or if we need anything more from you.')
            ->action('View my application', route('membership.show'));
    }
}
