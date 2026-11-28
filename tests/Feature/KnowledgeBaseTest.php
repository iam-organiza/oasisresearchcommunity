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
        ])->assertRedirect()->assertSessionHas('comment_sent', 'waiting');

        $comment = PostComment::where('author_email', 'amina@example.com')->firstOrFail();
        $this->assertSame(CommentStatus::Pending, $comment->status);
        $this->assertSame($parent->id, $comment->parent_id);
        $this->get(route('posts.show', $post->slug))->assertDontSee('Useful, thank you.');
    }

    public function test_comments_need_a_name_email_and_text(): void
    {
        $post = Post::factory()->create();

        // Back to the form, not the top of the page, in plain words.
        $this->post(route('posts.comments.store', $post->slug), ['author_email' => 'not-an-email'])
            ->assertRedirect(route('posts.show', $post->slug).'#comment-form')
            ->assertSessionHasErrors([
                'author_name' => 'Enter your name.',
                'author_email' => 'Enter an email address like name@example.com.',
                'content' => 'Write your comment.',
            ]);

        // A reply keeps its place.
        $parent = PostComment::factory()->for($post)->create();
        $this->post(route('posts.comments.store', $post->slug), ['parent_id' => $parent->id])
            ->assertRedirect(route('posts.show', ['slug' => $post->slug, 'reply' => $parent->id]).'#comment-form');
    }

    public function test_the_form_uses_the_sites_messages_and_fills_in_a_logged_in_member(): void
    {
        $post = Post::factory()->create();

        $this->get(route('posts.show', $post->slug))
            ->assertSee('novalidate', false)
            ->assertSee('To have a comment of yours removed, email');

        $member = User::factory()->create(['first_name' => 'Amina', 'last_name' => 'Yusuf', 'email' => 'amina@example.com']);
        $this->actingAs($member)->get(route('posts.show', $post->slug))
            ->assertSee('value="Amina Yusuf"', false)
            ->assertSee('value="amina@example.com"', false);
    }

    public function test_too_many_comments_go_back_to_the_form_with_the_text(): void
    {
        $post = Post::factory()->create();
        $comment = ['author_name' => 'A', 'author_email' => 'a@example.com', 'content' => 'Hello'];

        foreach (range(1, 5) as $n) {
            $this->post(route('posts.comments.store', $post->slug), $comment)->assertSessionHasNoErrors();
        }

        $this->post(route('posts.comments.store', $post->slug), ['content' => 'The sixth'] + $comment)
            ->assertRedirect(route('posts.show', $post->slug).'#comment-form')
            ->assertSessionHasErrors(['content' => 'You have sent several comments in the last minute. Wait a minute, then send this one again; it is still here.'])
            ->assertSessionHasInput('content', 'The sixth');
        $this->assertSame(5, PostComment::count());
    }

    public function test_an_editors_comment_goes_up_straight_away(): void
    {
        $post = Post::factory()->create();
        $editor = User::factory()->create();
        $editor->forceFill(['role' => UserRole::Editor])->save();

        $this->actingAs($editor)->post(route('posts.comments.store', $post->slug), [
            'author_name' => 'Editor', 'author_email' => 'ed@example.com', 'content' => 'Thanks for reading.',
        ])->assertSessionHas('comment_sent', 'published');

        $this->assertSame(CommentStatus::Approved, PostComment::firstOrFail()->status);
        $this->get(route('posts.show', $post->slug))->assertSee('Thanks for reading.');
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

    public function test_page_titles_are_escaped_once_and_terms_list_their_clauses(): void
    {
        $this->get(route('terms'))
            ->assertOk()
            ->assertSee('<title>Terms &amp; Conditions - ', false)
            ->assertDontSee('&amp;amp;', false)
            ->assertSee('href="#clause-1"', false)
            ->assertSee('<h3 id="clause-10">', false);

        $this->get(route('posts.index', ['search' => '<b>x</b>']))
            ->assertOk()
            ->assertDontSee('<b>x</b>', false);
    }
}
