<?php

namespace App\Console\Commands;

use App\Account\ExchangeRate;
use App\Enums\UserRole;
use App\Membership\MembershipMail;
use App\Models\User;
use App\Notifications\Account\ExchangeRateStale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Fetches today's dollar rate. If the rate in use is over a week old, the
 * admins are emailed, at most once a week, so a broken source is noticed.
 */
class RefreshExchangeRate extends Command
{
    protected $signature = 'fees:refresh-rate';

    protected $description = "Fetch the day's naira-to-dollar rate and warn the admins if it is out of date";

    public function handle(ExchangeRate $rates): int
    {
        $rate = $rates->refresh();
        $this->line($rate === null ? 'Could not fetch a rate; the last good one stays in use.' : "₦{$rate} to the dollar.");

        if ($rates->isStale() && Cache::add('exchange-rate-stale-alert', true, now()->addDays(7))) {
            MembershipMail::send(
                User::query()->where('role', UserRole::Admin->value)->get(),
                new ExchangeRateStale($rates->latest()['fetched_at'] ?? null),
            );
            $this->warn('The rate is out of date; the admins have been emailed.');
        }

        return self::SUCCESS;
    }
}
