<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Member = 'member';

    /**
     * Staff may manage site content such as Knowledge Base posts.
     */
    public function isStaff(): bool
    {
        return $this !== self::Member;
    }
}
