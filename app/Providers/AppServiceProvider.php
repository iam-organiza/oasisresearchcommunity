<?php

namespace App\Providers;

use App\Account\ExchangeRate;
use App\Account\Fees;
use App\Models\User;
use Carbon\CarbonImmutable;
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

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
