<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $member_ref
 * @property string|null $avatar
 * @property string|null $title
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $position
 * @property string|null $speciality
 * @property string|null $job_description
 * @property-read string $full_name
 * @property-read string|null $avatar_url
 */
#[Fillable(['avatar', 'title', 'first_name', 'middle_name', 'last_name', 'position', 'speciality', 'job_description'])]
class FeaturedMember extends Model
{
    protected static function booted(): void
    {
        static::creating(function (FeaturedMember $member): void {
            $member->member_ref ??= (string) Str::uuid();
        });
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->title, $this->first_name, $this->middle_name, $this->last_name,
        ])));
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return Post::mediaUrl($this->avatar);
    }
}
