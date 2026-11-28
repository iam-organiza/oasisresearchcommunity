<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $member_ref
 * @property int $sort_order
 * @property bool $is_visible
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
#[Fillable(['avatar', 'title', 'first_name', 'middle_name', 'last_name', 'position', 'speciality', 'job_description', 'is_visible'])]
class FeaturedMember extends Model
{
    protected static function booted(): void
    {
        static::creating(function (FeaturedMember $member): void {
            $member->member_ref ??= (string) Str::uuid();
            // A new member joins the end of the list.
            $member->sort_order = (int) static::query()->max('sort_order') + 1;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    /**
     * In the order chosen on the Website screen.
     *
     * @param  Builder<FeaturedMember>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The ones shown on the home page.
     *
     * @param  Builder<FeaturedMember>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_visible', true);
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
