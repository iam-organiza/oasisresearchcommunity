<?php

namespace App\Enums\Account;

/**
 * How a payment reached OASIS. The Registrar records bank transfers and
 * cash; members pay online through Paystack. "Before the website" is for
 * fees received before the account pages existed.
 */
enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case BeforeWebsite = 'before_website';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Bank transfer',
            self::Cash => 'Cash',
            self::BeforeWebsite => 'Received before the website',
            self::Online => 'Paid online (Paystack)',
        };
    }

    /**
     * The methods the Registrar can pick when recording a payment.
     *
     * @return list<self>
     */
    public static function recordable(): array
    {
        return [self::BankTransfer, self::Cash];
    }
}
