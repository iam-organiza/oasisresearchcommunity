<?php

namespace App\Enums\Membership;

use App\Account\ExchangeRate;
use App\Account\Fees;

/**
 * The four individual membership grades, highest first. Fees are set on the
 * Fees screen (App\Account\Fees); the criteria live in config/membership.php.
 */
enum Grade: string
{
    case Fellow = 'fellow';
    case Member = 'member';
    case Graduate = 'graduate';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Fellow => 'Fellow',
            self::Member => 'Member',
            self::Graduate => 'Graduate Member',
            self::Student => 'Student Member',
        };
    }

    /**
     * The part of a membership number that names the grade, as in OASIS/FEL/2026/0012.
     */
    public function code(): string
    {
        return match ($this) {
            self::Fellow => 'FEL',
            self::Member => 'MEM',
            self::Graduate => 'GRD',
            self::Student => 'STU',
        };
    }

    public function inductionFee(): int
    {
        return app(Fees::class)->induction($this);
    }

    public function annualDues(): int
    {
        return app(Fees::class)->subscription($this);
    }

    /**
     * @return list<string>
     */
    public function criteria(): array
    {
        return config("membership.grades.{$this->value}.criteria", []);
    }

    /**
     * @return array{value: string, label: string, induction_fee: int, annual_dues: int, induction_usd: int|null, annual_usd: int|null, criteria: list<string>}
     */
    public function toOption(): array
    {
        $rates = app(ExchangeRate::class);

        return [
            'value' => $this->value,
            'label' => $this->label(),
            'induction_fee' => $this->inductionFee(),
            'annual_dues' => $this->annualDues(),
            'induction_usd' => $rates->dollars($this->inductionFee()),
            'annual_usd' => $rates->dollars($this->annualDues()),
            'criteria' => $this->criteria(),
        ];
    }
}
