<?php

namespace App\Account;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Naira per dollar, to show what a naira price is in dollars. Fetched once
 * a day from ExchangeRate-API's free open endpoint (no key; their terms ask
 * for a link back, see ATTRIBUTION_URL), as OASIS Journals does. A failed
 * fetch keeps the last good rate. Staff can set a rate by hand in an
 * emergency, which wins until cleared.
 *
 * A dollar price is the naira price at this rate plus the margin in
 * config/membership.php, rounded up to a whole dollar, so paying in
 * dollars is never cheaper (OASIS, 2026-11-27).
 */
final class ExchangeRate
{
    public const SOURCE = 'https://open.er-api.com/v6/latest/USD';

    public const ATTRIBUTION_URL = 'https://www.exchangerate-api.com';

    /** Anything outside this range is a bad reading and is never stored. */
    private const SANE = [100, 100000];

    /** A rate older than this is stale; admins are told. */
    public const STALE_AFTER_DAYS = 7;

    public function __construct(private Fees $fees) {}

    /**
     * Fetches today's rate and stores it. Returns it, or null if the source
     * failed or gave a bad reading (the last good rate stays in use).
     */
    public function refresh(): ?float
    {
        try {
            $response = Http::timeout(10)->connectTimeout(5)->get(self::SOURCE);
            $rate = (float) $response->json('rates.NGN', 0);
            $ok = $response->successful() && $response->json('result') === 'success';
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if (! $ok || $rate < self::SANE[0] || $rate > self::SANE[1]) {
            logger()->warning('ExchangeRate: no sane USD to NGN rate', ['rate' => $rate]);

            return null;
        }

        DB::table('exchange_rates')->insert([
            'naira_per_dollar' => round($rate, 4), 'source' => 'open.er-api.com', 'fetched_at' => now(),
        ]);

        return round($rate, 4);
    }

    /**
     * @return array{rate: float, fetched_at: CarbonImmutable}|null the last good fetched rate
     */
    public function latest(): ?array
    {
        $row = DB::table('exchange_rates')->orderByDesc('fetched_at')->orderByDesc('id')->first();

        return $row ? ['rate' => (float) $row->naira_per_dollar, 'fetched_at' => CarbonImmutable::parse($row->fetched_at)] : null;
    }

    /**
     * The rate in use: the one set by hand if any, else the last fetched.
     */
    public function current(): ?float
    {
        return $this->fees->rateOverride() ?? $this->latest()['rate'] ?? null;
    }

    public function isStale(): bool
    {
        if ($this->fees->rateOverride() !== null) {
            return false;
        }
        $latest = $this->latest();

        return $latest === null || $latest['fetched_at']->lt(now()->subDays(self::STALE_AFTER_DAYS));
    }

    /**
     * A naira amount in whole dollars, with the margin, rounded up. Null
     * when there is no rate yet.
     */
    public function dollars(int $naira): ?int
    {
        $rate = $this->current();
        if ($rate === null || $rate <= 0) {
            return null;
        }
        $margin = 1 + ((int) config('membership.dollar_margin_percent')) / 100;

        // Round to cents first so float noise cannot push a whole-dollar amount up by one.
        return (int) ceil(round($naira / $rate * $margin, 2));
    }
}
