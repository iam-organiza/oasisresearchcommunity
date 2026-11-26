<?php

namespace App\Enums\Account;

/**
 * How much of a charge a waiver takes off: all of it, a fixed number of
 * naira, or a percentage.
 */
enum WaiverType: string
{
    case Full = 'full';
    case Amount = 'amount';
    case Percent = 'percent';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Waive it in full',
            self::Amount => 'Take off a fixed amount',
            self::Percent => 'Take off a percentage',
        };
    }

    /**
     * The naira this waiver takes off a charge of the given amount.
     */
    public function waives(int $chargeAmount, ?int $value): int
    {
        return match ($this) {
            self::Full => $chargeAmount,
            self::Amount => min($chargeAmount, (int) $value),
            self::Percent => (int) round($chargeAmount * min(100, (int) $value) / 100),
        };
    }
}
