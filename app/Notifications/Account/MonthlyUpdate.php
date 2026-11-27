<?php

namespace App\Notifications\Account;

use App\Account\AccountStatement;
use App\Account\Subscriptions;
use App\Models\Membership;
use App\Models\Post;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * The newsletter on the 25th (OASIS, 2026-11-27): where the member's
 * membership stands, so the reminder is not only about money, and what is
 * new in the Knowledge Base. Members can stop it; receipts and subscription
 * reminders still come.
 */
class MonthlyUpdate extends Notification
{
    /**
     * @param  Collection<int, Post>  $posts
     */
    public function __construct(public Collection $posts) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        /** @var Membership $membership */
        $membership = $notifiable->membership;
        $next = Subscriptions::next($membership);

        $totals = AccountStatement::for($notifiable)['totals'];

        return (new MailMessage)
            ->subject('Your OASIS Research Community update for '.now()->format('F Y'))
            ->markdown('mail.monthly-update', [
                'user' => $notifiable,
                'membership' => $membership,
                'next' => $next?->loadMissing('payments'),
                'owed' => $totals['owed'],
                'dueNow' => $totals['due_now'],
                'posts' => $this->posts,
                'month' => now()->format('F'),
                'unsubscribeUrl' => URL::signedRoute('newsletter.unsubscribe', ['user' => $notifiable->id]),
            ]);
    }
}
