<?php

namespace App\Models;

use App\Enums\CommentStatus;
use Database\Factories\PostCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $post_id
 * @property int|null $parent_id
 * @property string $author_name
 * @property string $author_email
 * @property string $content
 * @property CommentStatus $status
 * @property Carbon|null $created_at
 * @property-read Post $post
 * @property-read PostComment|null $parent
 * @property-read int|null $approved_replies_count Loaded by withCount() on the moderation queue.
 */
#[Fillable(['parent_id', 'author_name', 'author_email', 'content', 'status'])]
class PostComment extends Model
{
    /** @use HasFactory<PostCommentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CommentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<PostComment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(PostComment::class, 'parent_id');
    }

    /**
     * @return HasMany<PostComment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(PostComment::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
