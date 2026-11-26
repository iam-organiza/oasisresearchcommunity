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

        $mail = (new MailMessage)
            ->subject(match ($this->kind) {
                'passed' => "You passed the {$grade} exam",
                default => "Your {$grade} exam result",
            })
            ->greeting("Hello {$notifiable->first_name},");

        return match ($this->kind) {
            'passed' => $mail
                ->line("Congratulations: you scored {$score}% and passed the {$grade} exam.")
                ->line('You are admitted: a separate email has your induction fee, with the exam fee taken off. Your membership becomes active, with its number, once it is paid.')
                ->action('View my membership', route('membership.show')),
            'try_again' => $mail
                ->line("You scored {$score}%. The pass mark is ".Paper::PASS_PERCENT.'%.')
                ->line($left === 1 ? 'You have one more attempt on your exam fee, and you can take it whenever you are ready.' : "You have {$left} more attempts on your exam fee, and you can take them whenever you are ready.")
                ->line('Your exam page shows how you did in each area, and the prep materials are there to help.')
                ->action('Go to my exam page', route('membership.exam')),
            default => $mail
                ->line("You scored {$score}%. That was your third attempt on your exam fee.")
                ->line('To try again, pay the exam fee again from your exam page. Your next attempt opens '.Exam::WAIT_HOURS.' hours after this one.')
                ->action('Go to my exam page', route('membership.exam')),
        };
    }
}
