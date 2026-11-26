<?php

namespace App\Notifications\Membership;

use App\Membership\GradeRanking;
use App\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To the registrar: an application, appeal or resubmission is waiting for a decision.
 */
class ApplicationWaiting extends Notification
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
        $name = $this->application->user->name;
        $grade = $this->application->grade?->label() ?? 'a grade';

        $offered = $this->application->offered_grade?->label();
        $flags = collect($this->application->flags ?? [])
            ->map(fn (string $flag) => GradeRanking::FLAGS[$flag] ?? $flag)->implode('; ');

        $mail = (new MailMessage)
            ->subject(match ($this->kind) {
                'appealed' => "Appeal waiting: {$name}",
                'resubmitted' => "Updated application to check: {$name}",
                default => "Application to check: {$name}",
            })
            ->greeting("Hello {$notifiable->first_name},")
            ->line(match ($this->kind) {
                'appealed' => "{$name} was offered {$offered} and has appealed to be admitted as {$grade}, with their reasons.",
                'resubmitted' => "{$name} has sent their application back with more information. The site has offered them {$offered}.",
                default => "{$name} applied to join as {$grade}. The site has offered them {$offered}.",
            });

        if ($this->kind !== 'appealed' && $flags !== '') {
            $mail->line("**Worth a look:** {$flags}.")
                ->line('You can let the offer stand, correct it, or ask them for more.');
        }

        return $mail->action('Open the application', route('admin.applications.show', $this->application));
    }
}
