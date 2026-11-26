<?php

namespace App\Enums\Membership;

/**
 * How an applicant qualifies: by direct election on their evidence, through
 * the Researchers' Certification Programme, or by the membership exam.
 */
enum Route: string
{
    case DirectElection = 'direct';
    case CertificationProgramme = 'certification';
    case Exam = 'exam';

    public function label(): string
    {
        return match ($this) {
            self::DirectElection => 'Direct election on my qualifications and experience',
            self::CertificationProgramme => "Researchers' Certification Programme",
            self::Exam => 'The OASIS membership exam',
        };
    }
}
