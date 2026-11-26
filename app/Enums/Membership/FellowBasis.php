<?php

namespace App\Enums\Membership;

/**
 * What a direct application to Fellow rests on (OASIS, 2026-11-26).
 */
enum FellowBasis: string
{
    case Phd = 'phd';
    case Lecturer = 'lecturer';
    case Consultant = 'consultant';
    case Leader = 'leader';
    case Experience = 'experience';

    public function label(): string
    {
        return match ($this) {
            self::Phd => 'I have a PhD',
            self::Lecturer => 'I am a senior lecturer or above',
            self::Consultant => 'I am a research consultant with evidence on my CV',
            self::Leader => 'I lead an organisation or hold or held public office, and my work relies on research',
            self::Experience => 'I have 10+ years of research experience and 12+ publications',
        };
    }
}
