<?php

namespace App\Models;

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Enums\PostVisibility;
use Carbon\CarbonInterface;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $post_ref
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string $content
 * @property string|null $meta_title
 * @property string|null $canonical_url
 * @property string|null $meta_description
 * @property string|null $og_title
 * @property string|null $og_description
 * @property int $reading_time
 * @property PostStatus $status
 * @property PostVisibility $visibility
 * @property string $author_name
 * @property int|null $author_id
 * @property int|null $post_category_id
 * @property bool $allow_comments
 * @property bool $is_featured
 * @property bool $is_editors_choice
 * @property bool $is_popular
 * @property string $content_type
 * @property Carbon|null $published_at
 * @property string|null $featured_image
 * @property string|null $og_image
 * @property Carbon|null $created_at
 * @property-read PostCategory|null $category
 * @property-read string|null $featured_image_url
 * @property-read string $summary
 */
#[Fillable([
    'title', 'slug', 'excerpt', 'content', 'meta_title', 'canonical_url',
    'meta_description', 'og_title', 'og_description', 'reading_time', 'status',
    'visibility', 'author_name', 'post_category_id', 'allow_comments',
    'is_featured', 'is_editors_choice', 'is_popular', 'content_type',
    'published_at', 'featured_image', 'og_image',
])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Post $post): void {
            $post->post_ref ??= 'POST-'.Str::upper(Str::random(10));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'visibility' => PostVisibility::class,
            'allow_comments' => 'boolean',
            'is_featured' => 'boolean',
            'is_editors_choice' => 'boolean',
            'is_popular' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PostCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'post_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return HasMany<PostComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    /**
     * @return HasMany<PostComment, $this>
     */
    public function approvedComments(): HasMany
    {
        return $this->comments()->where('status', CommentStatus::Approved);
    }

    /**
     * Posts anyone may read: published, or scheduled with the time reached,
     * and not marked private.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query
            ->where('visibility', PostVisibility::Public)
            ->where(function (Builder $q): void {
                $q->where('status', PostStatus::Published)
                    ->orWhere(function (Builder $q): void {
                        $q->where('status', PostStatus::Scheduled)
                            ->where('published_at', '<=', now());
                    });
            });
    }

    /**
     * Newest first, by the date readers see.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeNewest(Builder $query): void
    {
        $query->orderByRaw('COALESCE(published_at, created_at) DESC')->orderByDesc('id');
    }

    public function isLive(): bool
    {
        return $this->visibility === PostVisibility::Public
            && ($this->status === PostStatus::Published
                || ($this->status === PostStatus::Scheduled && $this->published_at?->isPast()));
    }

    public function displayDate(): ?CarbonInterface
    {
        return $this->published_at ?? $this->created_at;
    }

    public function getFeaturedImageUrlAttribute(): ?string
    {
        return self::mediaUrl($this->featured_image);
    }

    /**
     * The excerpt, or the opening of the article when none was written.
     */
    public function getSummaryAttribute(): string
    {
        return $this->excerpt ?: Str::limit(trim(strip_tags($this->content)), 160);
    }

    /**
     * Stored image paths are relative to the public folder, as on the first
     * site ("assets/media/blog_images/x.jpg"); full addresses pass through.
     */
    public static function mediaUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://', '/']) ? $path : '/'.$path;
    }
}
