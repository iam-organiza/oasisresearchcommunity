<?php

namespace Tests\Feature;

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Models\FeaturedMember;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostComment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportLegacyContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('legacy');
        $legacy = Schema::connection('legacy');

        // The four tables as the first site defines them.
        $legacy->create('blog_categories', function (Blueprint $t) {
            $t->increments('id');
            $t->string('categoryRef')->nullable();
            $t->string('name');
            $t->string('slug');
            $t->text('description')->nullable();
            $t->timestamp('created_at')->nullable();
        });
        $legacy->create('blog_posts', function (Blueprint $t) {
            $t->increments('id');
            $t->string('postRef');
            $t->string('title');
            $t->string('slug');
            $t->text('excerpt')->nullable();
            $t->text('content');
            foreach (['meta_title', 'canonical_url', 'meta_description', 'og_title', 'og_description', 'featured_image', 'og_image', 'tags'] as $column) {
                $t->text($column)->nullable();
            }
            $t->integer('reading_time')->default(0);
            $t->string('status')->default('Draft');
            $t->string('author');
            $t->string('category');
            $t->string('visibility')->default('public');
            foreach (['allow_comments', 'is_featured', 'is_editors_choice', 'is_popular'] as $column) {
                $t->boolean($column)->default(false);
            }
            $t->string('content_type')->default('text');
            $t->dateTime('publish_date')->nullable();
            $t->dateTime('created_at')->nullable();
        });
        $legacy->create('blog_comments', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('post_id');
            $t->integer('parent_id')->nullable();
            $t->string('author_name');
            $t->string('author_email');
            $t->text('content');
            $t->string('status')->default('Pending');
            $t->dateTime('created_at')->nullable();
        });
        $legacy->create('featured_members', function (Blueprint $t) {
            $t->increments('id');
            $t->string('memberRef');
            foreach (['avatar', 'title', 'first_name', 'last_name', 'middle_name', 'position', 'speciality', 'job_description'] as $column) {
                $t->text($column)->nullable();
            }
            $t->timestamp('created_at')->nullable();
        });

        $db = DB::connection('legacy');
        $db->table('blog_categories')->insert(['categoryRef' => 'CAT-1', 'name' => 'research', 'slug' => 'research']);
        $db->table('blog_posts')->insert([
            'id' => 40, 'postRef' => 'POST-ABC', 'title' => 'Writing strong research questions',
            'slug' => 'writing-strong-research-questions', 'content' => '<p>Text</p>',
            'status' => 'Published', 'author' => 'Admin', 'category' => 'research',
            'tags' => 'Research Design, Questions', 'allow_comments' => 1, 'is_featured' => 1,
            'publish_date' => '2026-04-02 10:00:00', 'created_at' => '2026-04-01 09:00:00',
            'featured_image' => 'assets/media/blog_images/img_1.jpg',
        ]);
        $db->table('blog_posts')->insert([
            'id' => 41, 'postRef' => 'POST-DEF', 'title' => 'Orphan category', 'slug' => 'orphan-category',
            'content' => '<p>Text</p>', 'status' => 'Archived', 'author' => 'Editor', 'category' => 'social',
            'visibility' => 'private',
        ]);
        $db->table('blog_comments')->insert([
            ['id' => 7, 'post_id' => 40, 'parent_id' => null, 'author_name' => 'A', 'author_email' => 'a@example.com', 'content' => 'First', 'status' => 'Approved'],
            ['id' => 9, 'post_id' => 40, 'parent_id' => 7, 'author_name' => 'B', 'author_email' => 'b@example.com', 'content' => 'Reply', 'status' => 'Pending'],
        ]);
        $db->table('featured_members')->insert([
            'memberRef' => '2f7839e1-dd1f-473e-ac03-126f8c508eb0', 'title' => 'Dr.', 'first_name' => 'Usman',
            'middle_name' => '', 'last_name' => 'Lawan', 'position' => 'Fellow', 'avatar' => 'assets/media/avatars/a.jpeg',
        ]);
    }

    public function test_it_copies_posts_comments_and_featured_members_and_can_run_twice(): void
    {
        $this->artisan('orc:import-legacy-content')->assertSuccessful();
        $this->artisan('orc:import-legacy-content')->assertSuccessful();

        $this->assertSame(2, Post::count());
        $this->assertSame(2, PostCategory::count());
        $this->assertSame(2, PostComment::count());
        $this->assertSame(1, FeaturedMember::count());

        $post = Post::where('post_ref', 'POST-ABC')->firstOrFail();
        $this->assertSame(PostStatus::Published, $post->status);
        $this->assertSame('research', $post->category?->slug);
        $this->assertSame('2026-04-02 10:00:00', $post->published_at?->toDateTimeString());
        $this->assertSame('/assets/media/blog_images/img_1.jpg', $post->featured_image_url);
        $this->assertSame(['Questions', 'Research Design'], $post->tags()->orderBy('name')->pluck('name')->all());
        $this->assertTrue($post->isLive());

        $reply = PostComment::where('content', 'Reply')->firstOrFail();
        $this->assertSame(CommentStatus::Pending, $reply->status);
        $this->assertSame(PostComment::where('content', 'First')->value('id'), $reply->parent_id);

        $this->assertFalse(Post::where('post_ref', 'POST-DEF')->firstOrFail()->isLive());
        $this->assertSame('Dr. Usman Lawan', FeaturedMember::firstOrFail()->full_name);
    }
}
