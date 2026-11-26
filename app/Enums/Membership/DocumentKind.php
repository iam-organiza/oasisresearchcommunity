<?php

namespace App\Enums\Membership;

enum DocumentKind: string
{
    case Cv = 'cv';
    case Certificate = 'certificate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cv => 'CV',
            self::Certificate => 'Certificate',
            self::Other => 'Other evidence',
        };
    }
}
