<?php

namespace App\Enums\Membership;

/**
 * Where an application stands (since 2026-11-30):
 * draft → offered (the site offers a grade on submitting) → accepted (then
 * the exam fee and the exam) → approved (on the register). An applicant
 * who appeals an offer goes to the Registrar ("submitted") and gets a new
 * offer. The Registrar can ask for more information or decline at any
 * point before approval.
 */
enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Offered = 'offered';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case MoreInformation = 'more_information';
    case Approved = 'approved';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'In progress',
            self::Offered => 'Grade offered',
            self::Submitted => 'With the Registrar',
            self::Accepted => 'Offer accepted',
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

    /**
     * Still being worked on: neither approved nor declined.
     */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Approved, self::Declined], true);
    }
}
