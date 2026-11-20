<?php

namespace Tests\Feature;

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Enums\PostVisibility;
use App\Enums\UserRole;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostComment;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeBaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_load(): void
    {
        Post::factory()->create(['title' => 'Snowball sampling explained', 'is_featured' => true]);

        $this->get(route('home'))->assertOk()->assertSee('Optimizing Outcomes');
        $this->get(route('knowledge-base'))->assertOk()->assertSee('Snowball sampling explained');
        $this->get(route('posts.index'))->assertOk()->assertSee('Snowball sampling explained');
        $this->get(route('terms'))->assertOk()->assertSee('User Terms and Agreements');
    }

    public function test_missing_pages_show_the_branded_not_found_page(): void
    {
        foreach (['/no-such-page', '/knowledge-base/no-such-post'] as $address) {
            $this->get($address)
                ->assertNotFound()
                ->assertSee("We can't find that page.", false)
                ->assertSee('Return Home');
        }
    }

    public function test_only_live_posts_are_listed(): void
    {
        Post::factory()->create(['title' => 'Published piece']);
        Post::factory()->draft()->create(['title' => 'Draft piece']);
        Post::factory()->create(['title' => 'Private piece', 'visibility' => PostVisibility::Private]);
        Post::factory()->create(['title' => 'Archived piece', 'status' => PostStatus::Archived]);
        Post::factory()->create(['title' => 'Due piece', 'status' => PostStatus::Scheduled, 'published_at' => now()->subHour()]);
        Post::factory()->create(['title' => 'Future piece', 'status' => PostStatus::Scheduled, 'published_at' => now()->addDay()]);

        $this->get(route('posts.index'))
            ->assertSee('Published piece')
            ->assertSee('Due piece')
            ->assertDontSee('Draft piece')
            ->assertDontSee('Private piece')
            ->assertDontSee('Archived piece')
            ->assertDontSee('Future piece');
    }

    public function test_posts_filter_by_category_tag_and_search(): void
    {
        $methods = PostCategory::factory()->create(['name' => 'Methods', 'slug' => 'methods']);
        $match = Post::factory()->create(['title' => 'Attrition bias', 'post_category_id' => $methods->id]);
        $match->tags()->sync(Tag::idsFor(['Survey Design']));
        Post::factory()->create(['title' => 'Digital brand identity']);

        foreach ([['category' => 'methods'], ['tag' => 'survey-design'], ['search' => 'attrition']] as $filter) {
            // The sidebar lists recent posts whatever the filter, so the
            // check is on the filtered list itself.
            $this->get(route('posts.index', $filter))
                ->assertOk()
                ->assertViewHas('posts', fn ($posts) => $posts->pluck('title')->all() === ['Attrition bias']);
        }
    }

    public function test_a_post_page_shows_the_article_and_approved_comments_only(): void
    {
        $post = Post::factory()->create(['content' => '<p>Body of the <strong>article</strong>.</p>']);
        PostComment::factory()->for($post)->create(['content' => 'Approved remark']);
        PostComment::factory()->for($post)->pending()->create(['content' => 'Waiting remark']);

        $this->get(route('posts.show', $post->slug))
            ->assertOk()
            ->assertSee($post->title)
            ->assertSee('<strong>article</strong>', false)
            ->assertSee('Approved remark')
            ->assertDontSee('Waiting remark');
    }

    public function test_a_draft_is_hidden_from_readers_but_staff_can_preview_it(): void
    {
        $post = Post::factory()->draft()->create();

        $this->get(route('posts.show', $post->slug))->assertNotFound();

        $member = User::factory()->create();
        $this->actingAs($member)->get(route('posts.show', $post->slug))->assertNotFound();

        $editor = User::factory()->create();
        $editor->forceFill(['role' => UserRole::Editor])->save();
        $this->actingAs($editor)->get(route('posts.show', $post->slug))->assertOk();
    }

    public function test_old_post_links_redirect_permanently(): void
    {
        $this->get('/post?slug=what-is-a-research-gap')
            ->assertStatus(301)
            ->assertRedirect(route('posts.show', 'what-is-a-research-gap'));
        $this->get('/blog')->assertStatus(301)->assertRedirect('/knowledge-base');
        $this->get('/signin')->assertStatus(301)->assertRedirect('/login');
    }

    public function test_a_reader_comment_waits_for_approval(): void
    {
        $post = Post::factory()->create();
        $parent = PostComment::factory()->for($post)->create();

        $this->post(route('posts.comments.store', $post->slug), [
            'author_name' => 'Amina Yusuf',
            'author_email' => 'amina@example.com',
            'content' => 'Useful, thank you.',
            'parent_id' => $parent->id,
        ])->assertRedirect()->assertSessionHas('comment_sent');

        $comment = PostComment::where('author_email', 'amina@example.com')->firstOrFail();
        $this->assertSame(CommentStatus::Pending, $comment->status);
        $this->assertSame($parent->id, $comment->parent_id);
        $this->get(route('posts.show', $post->slug))->assertDontSee('Useful, thank you.');
    }

    public function test_comments_need_a_name_email_and_text(): void
    {
        $post = Post::factory()->create();

        $this->post(route('posts.comments.store', $post->slug), ['author_email' => 'not-an-email'])
            ->assertSessionHasErrors(['author_name', 'author_email', 'content']);
    }

    public function test_closed_posts_refuse_comments_and_form_fillers_save_nothing(): void
    {
        $closed = Post::factory()->create(['allow_comments' => false]);
        $open = Post::factory()->create();
        $comment = ['author_name' => 'A', 'author_email' => 'a@example.com', 'content' => 'Hello'];

        $this->post(route('posts.comments.store', $closed->slug), $comment)->assertForbidden();
        $this->post(route('posts.comments.store', $open->slug), $comment + ['website' => 'http://spam.example'])
            ->assertRedirect();

        $this->assertSame(0, PostComment::count());
    }
}
