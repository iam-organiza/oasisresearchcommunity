<?php

namespace App\Enums\Membership;

/**
 * Every prospective member attends one induction event (company profile 3.3).
 */
enum InductionEvent: string
{
    case InteractiveForum = 'forum';
    case NationalConference = 'conference';
    case CertificationProgramme = 'certification';

    public function label(): string
    {
        return match ($this) {
            self::InteractiveForum => "Researchers' Interactive Forum",
            self::NationalConference => "National Researchers' Conference",
            self::CertificationProgramme => "Researchers' Certification Programme",
        };
    }
}
