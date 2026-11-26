<?php

namespace App\Enums\Membership;

/**
 * How an applicant qualifies: the online membership exam (the usual way),
 * the Researchers' Certification Programme, or a request to be exempted from
 * the exam, which the registrar decides by hand (OASIS, 2026-11-26).
 */
enum Route: string
{
    case Exam = 'exam';
    case CertificationProgramme = 'certification';
    case Exemption = 'exemption';

    public function label(): string
    {
        return match ($this) {
            self::Exam => 'The OASIS membership exam, taken online',
            self::CertificationProgramme => "Researchers' Certification Programme",
            self::Exemption => 'Ask to be exempted from the exam',
        };
    }
}
