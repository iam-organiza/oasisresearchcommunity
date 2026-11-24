<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 */
#[Fillable(['name', 'slug'])]
class Tag extends Model
{
    /**
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    /**
     * Turn typed tag names into tag ids, creating the ones that are new.
     *
     * @param  iterable<string>  $names
     * @return list<int>
     */
    public static function idsFor(iterable $names): array
    {
        $ids = [];

        foreach ($names as $name) {
            $name = trim($name);
            $slug = Str::slug($name);

            if ($slug === '') {
                continue;
            }

            $ids[] = static::firstOrCreate(['slug' => $slug], ['name' => $name])->id;
        }

        return array_values(array_unique($ids));
    }
}
