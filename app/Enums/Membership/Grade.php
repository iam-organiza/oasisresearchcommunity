<?php

namespace App\Enums\Membership;

/**
 * The four individual membership grades, highest first. Fees and criteria
 * live in config/membership.php so OASIS's figures can change without code.
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
        return (int) config("membership.grades.{$this->value}.induction_fee");
    }

    public function annualDues(): int
    {
        return (int) config("membership.grades.{$this->value}.annual_dues");
    }

    /**
     * @return list<string>
     */
    public function criteria(): array
    {
        return config("membership.grades.{$this->value}.criteria", []);
    }

    /**
     * @return array{value: string, label: string, induction_fee: int, annual_dues: int, criteria: list<string>}
     */
    public function toOption(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'induction_fee' => $this->inductionFee(),
            'annual_dues' => $this->annualDues(),
            'criteria' => $this->criteria(),
        ];
    }
}
