<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LogoutResponse;
use App\Http\Responses\RegisterResponse;
use App\Models\User;
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
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    private const LOCKOUT_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 15 * 60;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
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
        $key = 'login-lockout:'.sha1($email);

        if (RateLimiter::tooManyAttempts($key, self::LOCKOUT_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            throw ValidationException::withMessages([
                Fortify::username() => "Account is temporarily locked. Try again in {$minutes} minute(s).",
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user !== null && Hash::check((string) $request->input('password'), $user->password)) {
            RateLimiter::clear($key);

            return $user;
        }

        // Counted for unknown addresses too, so the answer does not reveal
        // which emails have accounts.
        $attempts = RateLimiter::hit($key, self::LOCKOUT_SECONDS);

        if ($attempts >= self::LOCKOUT_ATTEMPTS) {
            throw ValidationException::withMessages([
                Fortify::username() => 'Account locked after 5 failed attempts. Try again in 15 minutes.',
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
