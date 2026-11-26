<?php

namespace App\Enums\Account;

/**
 * What a charge on a member's account is for. Subscriptions carry the year
 * they cover.
 */
enum ChargeKind: string
{
    case Exam = 'exam';
    case Induction = 'induction';
    case Subscription = 'subscription';

    public function label(): string
    {
        return match ($this) {
            self::Exam => 'Exam fee',
            self::Induction => 'Induction fee',
            self::Subscription => 'Subscription',
        };
    }
}
