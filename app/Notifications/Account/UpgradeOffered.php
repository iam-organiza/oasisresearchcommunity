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
        $current = $notifiable->membership?->grade->label();

        return (new MailMessage)
            ->subject("You can move up to {$grade}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("The Registrar has offered to move you up to {$grade}.")
            ->action('Pay the upgrade fee', route('membership.account'))
            ->markdown('notifications::email', [
                'facts' => array_filter([
                    $current ? ['Your grade now', $current] : null,
                    ['Move up to', $grade],
                    ['Upgrade fee', '₦'.number_format($this->charge->amount)],
                ]),
                'whyTitle' => 'How the fee is worked out',
                'why' => [$this->rule.'.'],
                'next' => ['text' => "Pay the upgrade fee and you become {$grade} straight away. As in any year you pay an induction fee, there is no yearly subscription that year: your next one is due 12 months after you upgrade."],
            ]);
    }
}
