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
        // The site's flags only matter on a first look or a resubmission; an appeal or a pass has its own reason to open.
        $flags = in_array($this->kind, ['appealed', 'exam_passed'], true) ? [] : collect($this->application->flags ?? [])
            ->map(fn (string $flag) => GradeRanking::FLAGS[$flag] ?? $flag)->values()->all();

        return (new MailMessage)
            ->subject(match ($this->kind) {
                'appealed' => "Appeal waiting: {$name}",
                'exam_passed' => "Passed the exam: {$name}",
                'resubmitted' => "Updated application to check: {$name}",
                default => "Application to check: {$name}",
            })
            ->greeting("Hello {$notifiable->first_name},")
            ->line(match ($this->kind) {
                'appealed' => "{$name} has appealed against their grade offer, with their reasons.",
                'exam_passed' => "{$name} has passed the membership exam.",
                'resubmitted' => "{$name} has sent their application back with more information.",
                default => "{$name} has applied to join, and the site has made them an offer.",
            })
            ->action('Open the application', route('admin.applications.show', $this->application))
            ->markdown('notifications::email', [
                'facts' => $this->kind === 'exam_passed'
                    ? [['Applicant', $name], ['Grade', $offered], ['Best score', rtrim(rtrim(number_format((float) $this->application->exam_best_percent, 1), '0'), '.').'%']]
                    : [['Applicant', $name], ['Applied for', $grade], ['Offered', $offered]],
                'whyTitle' => 'Worth a look',
                'why' => $flags,
                'next' => ['text' => match ($this->kind) {
                    'appealed' => 'Read their reasons, then answer the appeal with an offer.',
                    'exam_passed' => 'Approve them to put them on the register.',
                    default => 'Let the offer stand, correct it, or ask them for more.',
                }],
            ]);
    }
}
