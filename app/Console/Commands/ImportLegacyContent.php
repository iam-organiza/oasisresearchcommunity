<?php

namespace App\Console\Commands;

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Enums\PostVisibility;
use App\Models\Tag;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

#[Signature('orc:import-legacy-content')]
#[Description('Copy Knowledge Base posts, categories, comments and featured members from the first OASIS site')]
class ImportLegacyContent extends Command
{
    public function handle(): int
    {
        $legacy = DB::connection('legacy');

        $counts = DB::transaction(function () use ($legacy): array {
            return [
                'categories' => $this->importCategories($legacy->table('blog_categories')->orderBy('id')->get()),
                'posts' => $this->importPosts($legacy->table('blog_posts')->orderBy('id')->get()),
                'comments' => $this->importComments(
                    $legacy->table('blog_comments')
                        ->join('blog_posts', 'blog_posts.id', '=', 'blog_comments.post_id')
                        ->orderBy('blog_comments.id')
                        ->get(['blog_comments.*', 'blog_posts.postRef']),
                ),
                'featured members' => $this->importFeaturedMembers($legacy->table('featured_members')->orderBy('id')->get()),
            ];
        });

        foreach ($counts as $what => $count) {
            $this->components->twoColumnDetail(ucfirst($what), (string) $count);
        }

        return self::SUCCESS;
    }

    /**
     * @param  iterable<stdClass>  $rows
     */
    private function importCategories(iterable $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $this->upsert('post_categories', ['slug' => $row->slug], [
                'name' => $row->name,
                'description' => $row->description ?: null,
            ], $row->created_at);
            $count++;
        }

        return $count;
    }

    /**
     * @param  iterable<stdClass>  $rows
     */
    private function importPosts(iterable $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            // The first site stored the category as its slug (sometimes its
            // name); a post whose category no longer exists gets one created.
            $categorySlug = Str::slug((string) $row->category);
            $categoryId = $categorySlug === '' ? null : (
                DB::table('post_categories')->where('slug', $categorySlug)->value('id')
                ?? DB::table('post_categories')->insertGetId([
                    'name' => $row->category, 'slug' => $categorySlug,
                    'created_at' => now(), 'updated_at' => now(),
                ])
            );

            $postId = $this->upsert('posts', ['post_ref' => $row->postRef], [
                'title' => $row->title,
                'slug' => $row->slug,
                'excerpt' => $row->excerpt ?: null,
                'content' => $row->content,
                'meta_title' => $row->meta_title ?: null,
                'canonical_url' => $row->canonical_url ?: null,
                'meta_description' => $row->meta_description ?: null,
                'og_title' => $row->og_title ?: null,
                'og_description' => $row->og_description ?: null,
                'reading_time' => (int) $row->reading_time,
                'status' => (PostStatus::tryFrom(Str::lower((string) $row->status)) ?? PostStatus::Draft)->value,
                'visibility' => (PostVisibility::tryFrom(Str::lower((string) $row->visibility)) ?? PostVisibility::Public)->value,
                'author_name' => $row->author,
                'post_category_id' => $categoryId,
                'allow_comments' => (bool) $row->allow_comments,
                'is_featured' => (bool) $row->is_featured,
                'is_editors_choice' => (bool) $row->is_editors_choice,
                'is_popular' => (bool) $row->is_popular,
                'content_type' => $row->content_type ?: 'text',
                'published_at' => $row->publish_date ?: null,
                'featured_image' => $row->featured_image ?: null,
                'og_image' => $row->og_image ?: null,
            ], $row->created_at);

            $tagIds = Tag::idsFor(explode(',', (string) $row->tags));
            DB::table('post_tag')->where('post_id', $postId)->delete();
            DB::table('post_tag')->insert(array_map(
                fn (int $tagId): array => ['post_id' => $postId, 'tag_id' => $tagId],
                $tagIds,
            ));

            $count++;
        }

        return $count;
    }

    /**
     * @param  iterable<stdClass>  $rows
     */
    private function importComments(iterable $rows): int
    {
        $count = 0;

        // Rows arrive oldest first, so a reply's parent is already in place.
        foreach ($rows as $row) {
            $postId = DB::table('posts')->where('post_ref', $row->postRef)->value('id');

            if ($postId === null) {
                continue;
            }

            $this->upsert('post_comments', ['legacy_id' => $row->id], [
                'post_id' => $postId,
                'parent_id' => $row->parent_id
                    ? DB::table('post_comments')->where('legacy_id', $row->parent_id)->value('id')
                    : null,
                'author_name' => $row->author_name,
                'author_email' => $row->author_email,
                'content' => $row->content,
                'status' => (CommentStatus::tryFrom(Str::lower((string) $row->status)) ?? CommentStatus::Pending)->value,
            ], $row->created_at);
            $count++;
        }

        return $count;
    }

    /**
     * @param  iterable<stdClass>  $rows
     */
    private function importFeaturedMembers(iterable $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $this->upsert('featured_members', ['member_ref' => $row->memberRef], [
                'avatar' => $row->avatar ?: null,
                'title' => $row->title ?: null,
                'first_name' => $row->first_name,
                'middle_name' => $row->middle_name ?: null,
                'last_name' => $row->last_name,
                'position' => $row->position ?: null,
                'speciality' => $row->speciality ?: null,
                'job_description' => $row->job_description ?: null,
            ], $row->created_at);
            $count++;
        }

        return $count;
    }

    /**
     * Insert the row, or update it when the key already exists, and return its id.
     *
     * @param  array<string, mixed>  $key
     * @param  array<string, mixed>  $values
     */
    private function upsert(string $table, array $key, array $values, ?string $createdAt): int
    {
        $id = DB::table($table)->where($key)->value('id');

        if ($id !== null) {
            DB::table($table)->where('id', $id)->update($values + ['updated_at' => now()]);

            return (int) $id;
        }

        return (int) DB::table($table)->insertGetId($key + $values + [
            'created_at' => $createdAt ?? now(),
            'updated_at' => now(),
        ]);
    }
}
