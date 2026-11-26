<?php

namespace App\Enums\Membership;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case MoreInformation = 'more_information';
    case Approved = 'approved';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'In progress',
            self::Submitted => 'Submitted',
            self::MoreInformation => 'More information needed',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
        };
    }

    /**
     * The applicant can still change their answers.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::MoreInformation], true);
    }
}
