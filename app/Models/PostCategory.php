<?php

namespace App\Models;

use Database\Factories\PostCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 */
#[Fillable(['name', 'slug', 'description'])]
class PostCategory extends Model
{
    /** @use HasFactory<PostCategoryFactory> */
    use HasFactory;

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
