<?php

namespace App\Notifications\Membership;

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

        return (new MailMessage)
            ->subject(match ($this->kind) {
                'appealed' => "Appeal waiting: {$name}",
                'resubmitted' => "Updated application waiting: {$name}",
                default => "New membership application: {$name}",
            })
            ->greeting("Hello {$notifiable->first_name},")
            ->line(match ($this->kind) {
                'appealed' => "{$name} has appealed to be admitted as {$grade}, with their reasons.",
                'resubmitted' => "{$name} has sent their application back with more information.",
                default => "{$name} has applied to join as {$grade}.",
            })
            ->action('Review the application', route('admin.applications.show', $this->application));
    }
}
