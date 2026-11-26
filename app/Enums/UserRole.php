<?php

namespace App\Enums;

/**
 * Staff roles split the staff area by job: editors run the Knowledge Base,
 * the registrar keeps the member register, and admins can do both and
 * choose who holds the other roles.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Registrar = 'registrar';
    case Member = 'member';

    /**
     * Any staff role, whatever area it covers.
     */
    public function isStaff(): bool
    {
        return $this !== self::Member;
    }

    /**
     * Posts, comments and featured members.
     */
    public function canManageContent(): bool
    {
        return in_array($this, [self::Admin, self::Editor], true);
    }

    /**
     * Membership: chapters now, applications and the register next.
     */
    public function canManageMembers(): bool
    {
        return in_array($this, [self::Admin, self::Registrar], true);
    }

    /**
     * Choosing who is an editor, the registrar or an admin.
     */
    public function canManageStaff(): bool
    {
        return $this === self::Admin;
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Editor => 'Editor',
            self::Registrar => 'Registrar',
            self::Member => 'Member',
        };
    }

    /**
     * What each role is for, shown on the Staff page.
     */
    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Everything, including choosing staff roles.',
            self::Editor => 'Posts, comments and featured members.',
            self::Registrar => 'Chapters and, soon, membership applications and the member register.',
            self::Member => 'No staff access.',
        };
    }
}
