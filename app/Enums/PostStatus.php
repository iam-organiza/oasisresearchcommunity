<?php

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Archived = 'archived';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
