<?php

namespace App\Account;

use App\Enums\Membership\Grade;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The fees in force, as staff set them on the Fees screen (price_settings).
 * One instance per request (see AppServiceProvider), so the table is read
 * once and a change made in the request is seen straight away.
 */
final class Fees
{
    /** @var Collection<string, int|null>|null */
    private ?Collection $amounts = null;

    public function exam(): int
    {
        return (int) $this->get('exam');
    }

    public function induction(Grade $grade): int
    {
        return (int) $this->get("induction.{$grade->value}");
    }

    public function subscription(Grade $grade): int
    {
        return (int) $this->get("subscription.{$grade->value}");
    }

    public function upgradePercent(): int
    {
        return (int) $this->get('upgrade_percent');
    }

    /** Postage for a posted induction pack; 0 until OASIS sets it. */
    public function postage(): int
    {
        return (int) $this->get('postage');
    }

    public function upgradeDiscountAfterOneYear(): int
    {
        return (int) $this->get('upgrade_discount_1y');
    }

    public function upgradeDiscountAfterTwoYears(): int
    {
        return (int) $this->get('upgrade_discount_2y');
    }

    public function promotionDiscount(): int
    {
        return (int) $this->get('promotion_discount');
    }

    /**
     * Naira per dollar set by hand for an emergency, or null when the daily
     * rate is used.
     */
    public function rateOverride(): ?int
    {
        $override = $this->get('usd_rate_override');

        return $override ? (int) $override : null;
    }

    public function get(string $key): ?int
    {
        $this->amounts ??= DB::table('price_settings')->pluck('amount', 'key')
            ->map(fn ($amount) => $amount === null ? null : (int) $amount);

        return $this->amounts->get($key);
    }

    /**
     * Saves new figures; each one that changed is logged with the reason.
     *
     * @param  array<string, int|null>  $amounts
     * @return list<string> the keys that changed
     */
    public function update(array $amounts, string $reason, ?User $user): array
    {
        $changed = DB::transaction(function () use ($amounts, $reason, $user): array {
            $current = DB::table('price_settings')->lockForUpdate()->pluck('amount', 'key');
            $changed = [];

            foreach ($amounts as $key => $amount) {
                $old = $current->get($key);
                $old = $old === null ? null : (int) $old;
                if (! $current->has($key) || $old === $amount) {
                    continue;
                }

                DB::table('price_settings')->where('key', $key)->update([
                    'amount' => $amount, 'updated_by' => $user?->id, 'updated_at' => now(),
                ]);
                DB::table('price_setting_changes')->insert([
                    'key' => $key, 'old_amount' => $old, 'new_amount' => $amount,
                    'reason' => $reason, 'user_id' => $user?->id, 'created_at' => now(),
                ]);
                $changed[] = $key;
            }

            return $changed;
        });

        $this->amounts = null;

        return $changed;
    }

    /**
     * What a key is called on the Fees screen and in its history.
     */
    public static function label(string $key): string
    {
        return match (true) {
            $key === 'exam' => 'Exam fee',
            $key === 'upgrade_percent' => 'Upgrade admin fee, first year (%)',
            $key === 'upgrade_discount_1y' => 'Upgrade discount after one year (%)',
            $key === 'upgrade_discount_2y' => 'Upgrade discount after two years (%)',
            $key === 'promotion_discount' => 'Promotion discount (%)',
            $key === 'postage' => 'Induction pack postage',
            $key === 'usd_rate_override' => 'Dollar rate set by hand',
            str_starts_with($key, 'induction.') => (Grade::tryFrom(substr($key, 10))?->label() ?? $key).' induction fee',
            str_starts_with($key, 'subscription.') => (Grade::tryFrom(substr($key, 13))?->label() ?? $key).' yearly subscription',
            default => $key,
        };
    }
}
