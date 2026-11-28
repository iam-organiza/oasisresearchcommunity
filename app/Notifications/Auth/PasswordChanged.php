<?php

namespace App\Notifications\Auth;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To the account holder whenever their password changes, by a reset link or
 * in Settings: if it was not them, they hear at once and can take it back.
 */
class PasswordChanged extends Notification
{
    public const VIA_RESET = 'reset';

    public const VIA_SETTINGS = 'settings';

    /**
     * @param  self::VIA_*  $via
     */
    public function __construct(public string $via, public CarbonImmutable $at) {}

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
            ->subject('Your OASIS Research Community password was changed')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('The password for your account has just been changed '
                .($this->via === self::VIA_RESET ? 'with a reset link sent to this address' : 'in Settings, while logged in')
                .', and any other devices logged in to it have been logged out.')
            ->line('If this was you, there is nothing more to do.')
            ->action('Reset my password', route('password.request'))
            ->markdown('notifications::email', [
                'facts' => [
                    ['Account', $notifiable->email],
                    ['When', $this->at->timezone(config('app.timezone'))->format('j M Y, g:i a')],
                ],
                'next' => [
                    'title' => 'Not you?',
                    'text' => 'Reset your password now; that logs everyone else out of the account. Then tell us at '.config('oasis.support_email').' so we can check it.',
                ],
            ]);
    }
}
