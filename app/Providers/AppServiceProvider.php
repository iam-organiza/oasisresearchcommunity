<?php

namespace App\Providers;

use App\Account\ExchangeRate;
use App\Account\Fees;
use App\Models\User;
use App\Support\PasswordRequirements;
use App\Support\ResetLink;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The fees are read once per request (or job) and fresh in the next.
        $this->app->scoped(Fees::class);
        $this->app->scoped(ExchangeRate::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureStaffAreas();
        $this->configureVerificationEmail();
        $this->configurePasswordResetEmail();
    }

    /**
     * The "reset your password" email: by name, in the site's words, saying how
     * long the link lasts and that ignoring it changes nothing.
     */
    protected function configurePasswordResetEmail(): void
    {
        ResetPassword::toMailUsing(function (CanResetPassword $notifiable, string $token): MailMessage {
            $minutes = ResetLink::minutes();
            $name = $notifiable instanceof User ? $notifiable->first_name : null;
            $email = $notifiable->getEmailForPasswordReset();

            return (new MailMessage)
                ->subject('Reset your OASIS Research Community password')
                ->greeting($name ? "Hello {$name}," : 'Hello,')
                ->line('Someone, hopefully you, asked to reset the password for your account.')
                ->line('If you did not ask for this, ignore this email; your password stays as it is.')
                ->action('Choose a new password', route('password.reset', ['token' => $token, 'email' => $email]))
                ->markdown('notifications::email', [
                    'facts' => [
                        ['Account', $email],
                        ['Link works for', "{$minutes} minutes, once"],
                    ],
                    'next' => [
                        'title' => 'Choose a new password',
                        'text' => 'Open the link and choose a new password. Saving it logs your account out on any other devices.',
                    ],
                ]);
        });
    }

    /**
     * The email sent after signing up: by name, in the site's words, with how
     * long the link lasts, laid out like the other membership emails.
     */
    protected function configureVerificationEmail(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            $minutes = (int) config('auth.verification.expire', 60);
            $name = $notifiable instanceof User ? $notifiable->first_name : null;

            return (new MailMessage)
                ->subject('Confirm your email for OASIS Research Community')
                ->greeting($name ? "Hello {$name}," : 'Hello,')
                ->line('Thank you for creating an account with OASIS Research Community. Confirm this is your email address, and you can apply for membership.')
                ->action('Confirm my email', $url)
                ->line("The link works for {$minutes} minutes. If it has expired, log in and send yourself a new one.")
                ->line('If you did not create an account, you can ignore this email; nothing more will happen.');
        });
    }

    /**
     * Each staff area has its own permission, so the registrar and editors
     * see only their part of the staff area.
     */
    protected function configureStaffAreas(): void
    {
        Gate::define('manage-content', fn (User $user): bool => $user->role->canManageContent());
        Gate::define('manage-members', fn (User $user): bool => $user->role->canManageMembers());
        Gate::define('manage-staff', fn (User $user): bool => $user->role->canManageStaff());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => PasswordRequirements::rule());
    }
}
