<?php

namespace App\Notifications\Account;

use App\Models\Charge;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The Registrar offered the member a higher grade; it takes effect once paid.
 */
class UpgradeOffered extends Notification
{
    public function __construct(public Charge $charge, public string $rule) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $grade = $this->charge->grade?->label() ?? 'a higher grade';

        return (new MailMessage)
            ->subject("You can move up to {$grade}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("The Registrar has offered to move you up to {$grade}.")
            ->line('**The upgrade fee:** ₦'.number_format($this->charge->amount).' ('.$this->rule.').')
            ->line('You become a '.$grade.' as soon as it is paid. As in any year you pay an induction fee, there is no yearly subscription that year: your next one is due 12 months after you upgrade.')
            ->action('Pay the upgrade fee', route('membership.account'));
    }
}
