<?php

namespace App\Notifications\Account;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To the admins: the daily dollar rate has not updated for a week, so the
 * dollar prices on the site may be out of date.
 */
class ExchangeRateStale extends Notification
{
    public function __construct(public ?CarbonImmutable $lastFetched) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('The dollar rate has not updated')
            ->greeting("Hello {$notifiable->first_name},")
            ->line($this->lastFetched
                ? "The website last got the naira-to-dollar rate on {$this->lastFetched->format('j F Y')}, so the dollar prices it shows may be out of date."
                : 'The website has not been able to get a naira-to-dollar rate yet, so it shows no dollar prices.')
            ->line('It tries again every morning. If this goes on, set a rate by hand on the Fees screen until it recovers.')
            ->action('Open the Fees screen', route('admin.fees.index'));
    }
}
