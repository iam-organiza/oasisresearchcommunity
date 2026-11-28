<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LogoutResponse;
use App\Http\Responses\PasswordResetLinkResponse;
use App\Http\Responses\PasswordResetResponse;
use App\Http\Responses\RegisterResponse;
use App\Models\User;
use App\Support\LoginLockout;
use App\Support\PasswordRequirements;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(PasswordResetResponseContract::class, PasswordResetResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::authenticateUsing($this->authenticateWithLockout(...));
    }

    /**
     * Check the email and password, locking the account for 15 minutes after
     * five wrong passwords in a row, as the first site did.
     */
    private function authenticateWithLockout(Request $request): ?User
    {
        $email = Str::lower((string) $request->input(Fortify::username()));

        if (LoginLockout::locked($email)) {
            throw ValidationException::withMessages([
                Fortify::username() => 'This account is locked for '.LoginLockout::timeLeft($email).' after too many wrong passwords. To get in now, reset your password.',
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user !== null && Hash::check((string) $request->input('password'), $user->password)) {
            LoginLockout::clear($email);

            return $user;
        }

        $left = LoginLockout::miss($email);

        if ($left === 0) {
            throw ValidationException::withMessages([
                Fortify::username() => 'That is five wrong passwords, so the account is locked for 15 minutes. To get in now, reset your password.',
            ]);
        }

        // A warning before the lock, while there is still time to reset instead.
        if ($left <= 2) {
            throw ValidationException::withMessages([
                Fortify::username() => 'That email and password do not match. '.($left === 1 ? 'One more wrong try locks' : 'Two more wrong tries lock').' the account for 15 minutes.',
            ]);
        }

        return null;
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
            'email' => $request->session()->get('email'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'passwordChecklist' => PasswordRequirements::checklist(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
            'email' => $request->user()?->email,
            'linkMinutes' => (int) config('auth.verification.expire', 60),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'passwordChecklist' => PasswordRequirements::checklist(),
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        // Sign-ups from one connection: enough for a family or an office, not
        // for a script making accounts or sending verification emails to anyone.
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(5)->by($request->ip())
            ->response(fn () => back()->withInput($request->except('password', 'password_confirmation'))->withErrors([
                'email' => 'Too many accounts have been created from this connection. Try again in an hour.',
            ])));

        // Fortify registers the sign-up route itself, without a limit, so the limit is added once routes are loaded.
        $this->app->booted(function (): void {
            $routes = $this->app->make(Router::class)->getRoutes();
            $routes->refreshNameLookups();
            $routes->getByName('register.store')?->middleware('throttle:register');
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
