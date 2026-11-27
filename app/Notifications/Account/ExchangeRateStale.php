<?php

namespace App\Notifications\Account;

use App\Account\ExchangeRate;
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
        $inUse = app(ExchangeRate::class)->current();

        return (new MailMessage)
            ->subject('The dollar rate has not updated')
            ->greeting("Hello {$notifiable->first_name},")
            ->line($this->lastFetched
                ? 'The website has not been able to update the naira-to-dollar rate for more than a week, so the dollar prices it shows may be out of date.'
                : 'The website has not been able to get a naira-to-dollar rate yet, so it shows no dollar prices.')
            ->action('Open the Fees screen', route('admin.fees.index'))
            ->markdown('notifications::email', [
                'facts' => [
                    ['Last updated', $this->lastFetched?->format('j M Y') ?? 'Never'],
                    ['Rate in use', $inUse !== null ? '₦'.number_format($inUse, 2).' to $1' : 'None'],
                ],
                'next' => ['text' => 'It tries again every morning. If this goes on, set a rate by hand on the Fees screen until it recovers.'],
            ]);
    }
}
