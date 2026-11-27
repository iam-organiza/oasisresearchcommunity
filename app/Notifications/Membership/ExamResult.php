<?php

namespace App\Notifications\Membership;

use App\Exam\Exam;
use App\Exam\Paper;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The result of an exam attempt: passed; not yet, with attempts left; or
 * not yet, with the fee to pay again. (A lower grade earned on three
 * attempts comes as a grade offer instead.)
 */
class ExamResult extends Notification
{
    /**
     * @param  'passed'|'try_again'|'pay_again'  $kind
     */
    public function __construct(public ExamAttempt $attempt, public string $kind) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $grade = $this->attempt->grade->label();
        $score = rtrim(rtrim(number_format((float) $this->attempt->percent, 2), '0'), '.');
        $left = Exam::ATTEMPTS_PER_FEE - $this->attempt->number;
        $facts = [['Your score', "{$score}%"], ['Pass mark', Paper::PASS_PERCENT.'%']];

        $mail = (new MailMessage)
            ->subject(match ($this->kind) {
                'passed' => "You passed the {$grade} exam",
                default => "Your {$grade} exam result",
            })
            ->greeting("Hello {$notifiable->first_name},");

        return match ($this->kind) {
            'passed' => $mail
                ->line("Congratulations: you passed the {$grade} exam, and you are admitted.")
                ->action('View my membership', route('membership.show'))
                ->markdown('notifications::email', [
                    'facts' => [['Grade', $grade], ...$facts],
                    'next' => ['text' => 'Pay your induction fee. A separate email has it, with the exam fee taken off. Your membership becomes active, with its number, once it is paid.'],
                ]),
            'try_again' => $mail
                ->line("You did not pass the {$grade} exam this time.")
                ->action('Go to my exam page', route('membership.exam'))
                ->markdown('notifications::email', [
                    'facts' => [...$facts, ['Attempts left', (string) $left]],
                    'next' => ['text' => ($left === 1 ? 'You have one more attempt on your exam fee' : "You have {$left} more attempts on your exam fee")
                        .($left === 1 ? '; take it' : '; take them').' whenever you are ready. Your exam page shows how you did in each area, and the prep materials are there to help.'],
                ]),
            default => $mail
                ->line("You did not pass the {$grade} exam. That was your third attempt on your exam fee.")
                ->action('Go to my exam page', route('membership.exam'))
                ->markdown('notifications::email', [
                    'facts' => [...$facts, ['Attempts left', '0']],
                    'next' => ['text' => 'To try again, pay the exam fee again from your exam page. Your next attempt opens '.Exam::WAIT_HOURS.' hours after this one.'],
                ]),
        };
    }
}
