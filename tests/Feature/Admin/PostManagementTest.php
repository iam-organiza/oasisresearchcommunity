<?php

namespace Tests\Feature\Admin;

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostManagementTest extends TestCase
{
    use RefreshDatabase;

    private function staff(UserRole $role = UserRole::Editor): User
    {
        $user = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Okoro']);
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'What is a research gap?',
            'slug' => '',
            'content' => '<p>'.str_repeat('word ', 450).'</p>',
            'author_name' => 'Ada Okoro',
            'post_category_id' => PostCategory::factory()->create()->id,
            'tags' => 'Research Design, Literature Review, research design',
            'status' => 'published',
            'visibility' => 'public',
            'allow_comments' => true,
        ];
    }

    public function test_members_and_guests_cannot_reach_staff_tools(): void
    {
        $post = Post::factory()->create();

        $this->get(route('admin.posts.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.posts.index'))->assertForbidden();
        $this->get(route('admin.comments.index'))->assertForbidden();
        $this->post(route('admin.posts.store'), $this->payload())->assertForbidden();
        $this->delete(route('admin.posts.destroy', $post))->assertForbidden();
        $this->assertSame(1, Post::count());
    }

    public function test_after_login_staff_land_on_their_tools_and_members_on_the_site(): void
    {
        $this->get(route('admin'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->get(route('admin'))->assertRedirect(route('home'));
        // From a React page (log in, confirm password) it must be a full page visit, not an in-app one.
        $version = (new HandleInertiaRequests)->version(request());
        $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version])->get(route('admin'))
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('home'));

        $this->actingAs($this->staff());
        $this->get(route('admin'))->assertRedirect(route('admin.posts.index'));
    }

    public function test_staff_see_the_post_list_and_forms(): void
    {
        $post = Post::factory()->create();
        $this->actingAs($this->staff());

        $this->get(route('admin.posts.index'))->assertOk();
        $this->get(route('admin.posts.create'))->assertOk();
        $this->get(route('admin.posts.edit', $post))->assertOk();
        $this->get(route('admin.comments.index'))->assertOk();
    }

    public function test_staff_can_publish_a_post(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->post(route('admin.posts.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $post = Post::firstOrFail();
        $this->assertSame('what-is-a-research-gap', $post->slug);
        $this->assertSame($staff->id, $post->author_id);
        $this->assertSame(3, $post->reading_time);
        $this->assertNotNull($post->published_at);
        $this->assertSame(['Literature Review', 'Research Design'], $post->tags()->orderBy('name')->pluck('name')->all());
        $this->get(route('posts.show', $post->slug))->assertOk();
    }

    public function test_a_post_needs_its_main_fields_and_a_unique_address(): void
    {
        Post::factory()->create(['slug' => 'taken']);
        $this->actingAs($this->staff());

        $this->post(route('admin.posts.store'), [])
            ->assertSessionHasErrors(['title', 'content', 'author_name', 'post_category_id', 'status'])
            // No title, so no address either: the title's message is the one that matters.
            ->assertSessionDoesntHaveErrors('slug');
        $this->post(route('admin.posts.store'), $this->payload(['slug' => 'taken']))
            ->assertSessionHasErrors('slug');
        $this->post(route('admin.posts.store'), $this->payload(['status' => 'scheduled']))
            ->assertSessionHasErrors('published_at');
    }

    public function test_staff_can_update_a_post_and_its_image(): void
    {
        Storage::fake('media');
        $post = Post::factory()->draft()->create();

        $this->actingAs($this->staff())
            ->put(route('admin.posts.update', $post), $this->payload([
                'title' => 'Updated title',
                'slug' => $post->slug,
                'status' => 'draft',
                'featured_image_file' => UploadedFile::fake()->image('cover.jpg'),
            ]))
            ->assertSessionHasNoErrors();

        $post->refresh();
        $this->assertSame('Updated title', $post->title);
        $this->assertSame(PostStatus::Draft, $post->status);
        $this->assertStringStartsWith('assets/media/blog_images/img_', (string) $post->featured_image);
        Storage::disk('media')->assertExists(str_replace('assets/media/', '', (string) $post->featured_image));
    }

    public function test_images_over_5mb_are_refused(): void
    {
        Storage::fake('media');

        $this->actingAs($this->staff())
            ->postJson(route('admin.uploads.image'), ['image' => UploadedFile::fake()->image('big.jpg')->size(6000)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');

        $this->postJson(route('admin.uploads.image'), ['image' => UploadedFile::fake()->image('ok.png')])
            ->assertOk()
            ->assertJsonStructure(['url']);
    }

    public function test_staff_can_delete_a_post_with_its_comments(): void
    {
        $post = Post::factory()->create();
        PostComment::factory()->for($post)->create();

        $this->actingAs($this->staff())->delete(route('admin.posts.destroy', $post))->assertRedirect();

        $this->assertSame(0, Post::count());
        $this->assertSame(0, PostComment::count());
    }

    public function test_staff_can_add_a_category_once(): void
    {
        $this->actingAs($this->staff());

        $this->post(route('admin.categories.store'), ['name' => 'Research Methods']);
        $this->post(route('admin.categories.store'), ['name' => 'research methods']);

        $this->assertSame(1, PostCategory::where('slug', 'research-methods')->count());
    }

    public function test_a_draft_needs_only_a_title(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.posts.store'), ['title' => 'Notes for later', 'author_name' => 'Ada Okoro', 'status' => 'draft', 'visibility' => 'public'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('inertia.flash_data.toast.message', 'Draft saved. Readers cannot see it yet.');

        $post = Post::firstOrFail();
        $this->assertSame('', $post->content);
        $this->assertNull($post->post_category_id);
        $this->assertFalse($post->isLive());

        // Going out needs the rest.
        $this->put(route('admin.posts.update', $post), ['title' => 'Notes for later', 'author_name' => 'Ada Okoro', 'status' => 'published', 'visibility' => 'public'])
            ->assertSessionHasErrors([
                'content' => 'Write the article before it goes out. A draft can be saved without it.',
                'post_category_id' => 'Choose a category before it goes out. A draft can be saved without one.',
            ]);
    }

    public function test_scripts_never_reach_the_page(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.posts.store'), $this->payload([
                'content' => '<p onclick="steal()">Hello <a href="javascript:alert(1)">link</a></p><script>alert(1)</script><iframe src="https://evil.test"></iframe><table><tr><td style="color: red">cell</td></tr></table>',
            ]))
            ->assertSessionHasNoErrors();

        $content = Post::firstOrFail()->content;
        $this->assertStringNotContainsString('script', $content);
        $this->assertStringNotContainsString('onclick', $content);
        $this->assertStringNotContainsString('javascript:', $content);
        $this->assertStringNotContainsString('iframe', $content);
        $this->assertStringContainsString('<td style="color: red">cell</td>', $content);

        // An article saved before cleaning existed is cleaned on the way out too.
        $old = Post::factory()->create(['content' => '<p>Old</p><script>alert(1)</script>']);
        $this->get(route('posts.show', $old->slug))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('<p>Old</p>', false);
    }

    public function test_the_publish_date_has_to_match_the_status(): void
    {
        $this->actingAs($this->staff());

        $this->post(route('admin.posts.store'), $this->payload(['published_at' => now()->addWeek()->format('Y-m-d\\TH:i')]))
            ->assertSessionHasErrors(['published_at' => 'That date is still to come. To publish then, set the status to Scheduled; to publish now, clear the date.']);
        $this->post(route('admin.posts.store'), $this->payload(['status' => 'scheduled', 'published_at' => now()->subDay()->format('Y-m-d\\TH:i')]))
            ->assertSessionHasErrors(['published_at' => 'A scheduled post needs a time still to come. To publish now, set the status to Published.']);

        $at = now()->addWeek()->setTime(9, 0);
        $this->post(route('admin.posts.store'), $this->payload(['status' => 'scheduled', 'published_at' => $at->format('Y-m-d\\TH:i')]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('inertia.flash_data.toast.message', 'Scheduled for '.$at->format('j M Y, g:i a').'.');

        // Once its time has passed, the scheduled post can still be edited without changing the date.
        $post = Post::firstOrFail();
        $this->travelTo($at->copy()->addDay());
        $this->put(route('admin.posts.update', $post), $this->payload(['status' => 'scheduled', 'slug' => $post->slug, 'published_at' => $at->format('Y-m-d\\TH:i'), 'post_category_id' => $post->post_category_id]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('inertia.flash_data.toast.message', 'Saved. It is on the site.');
    }

    public function test_the_toast_says_what_happened_to_the_post(): void
    {
        $this->actingAs($this->staff());

        $this->post(route('admin.posts.store'), $this->payload())
            ->assertSessionHas('inertia.flash_data.toast.message', 'Published. It is on the site now.');
        $post = Post::firstOrFail();

        $this->put(route('admin.posts.update', $post), $this->payload(['slug' => $post->slug, 'post_category_id' => $post->post_category_id]))
            ->assertSessionHas('inertia.flash_data.toast.message', 'Changes are live.');
        $this->put(route('admin.posts.update', $post), $this->payload(['slug' => $post->slug, 'post_category_id' => $post->post_category_id, 'visibility' => 'private']))
            ->assertSessionHas('inertia.flash_data.toast.message', 'Saved. Only editors and admins can read it.');
        $this->put(route('admin.posts.update', $post), $this->payload(['slug' => $post->slug, 'post_category_id' => $post->post_category_id, 'status' => 'archived']))
            ->assertSessionHas('inertia.flash_data.toast.message', 'Archived. Readers can no longer see it.');
    }

    public function test_the_main_image_can_be_removed(): void
    {
        $post = Post::factory()->create(['featured_image' => 'assets/media/blog_images/old.jpg']);

        $this->actingAs($this->staff())
            ->put(route('admin.posts.update', $post), $this->payload(['slug' => $post->slug, 'remove_featured_image' => true]))
            ->assertSessionHasNoErrors();

        $this->assertNull($post->fresh()->featured_image);
    }

    public function test_a_new_category_is_chosen_and_a_bad_name_is_explained(): void
    {
        $this->actingAs($this->staff());

        $this->post(route('admin.categories.store'), ['name' => 'Research Methods'])
            ->assertSessionHas('inertia.flash_data.newCategoryId', PostCategory::where('slug', 'research-methods')->value('id'));

        $this->post(route('admin.categories.store'), ['name' => '!!!'])
            ->assertSessionHasErrors(['name' => 'Give the category a name with letters or numbers in it.']);
    }

    public function test_staff_moderate_comments(): void
    {
        $post = Post::factory()->create();
        $comment = PostComment::factory()->for($post)->pending()->create(['content' => 'Held remark']);
        $this->actingAs($this->staff());

        $this->patch(route('admin.comments.update', $comment), ['status' => 'approved'])
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.message', 'Approved. It is on the post now.')
            ->assertSessionHas('inertia.flash_data.toast.undo.data', ['status' => 'pending', 'reopen' => []]);
        $this->assertSame(CommentStatus::Approved, $comment->refresh()->status);
        $this->get(route('posts.show', $post->slug))->assertSee('Held remark');

        $reply = PostComment::factory()->for($post)->create(['parent_id' => $comment->id]);
        $this->patch(route('admin.comments.update', $comment), ['status' => 'rejected'])
            ->assertSessionHas('inertia.flash_data.toast.message', 'Rejected. It stays off the site. Its reply went back to Waiting.');
        $this->assertSame(CommentStatus::Pending, $reply->refresh()->status);

        // Undo puts the comment and its reply back up.
        $this->patch(route('admin.comments.update', $comment), ['status' => 'approved', 'reopen' => [$reply->id]]);
        $this->assertSame(CommentStatus::Approved, $comment->refresh()->status);
        $this->assertSame(CommentStatus::Approved, $reply->refresh()->status);

        $this->delete(route('admin.comments.destroy', $comment))->assertRedirect();
        $this->assertSame(0, PostComment::count());
    }

    public function test_a_reply_under_a_hidden_comment_cannot_be_approved(): void
    {
        $post = Post::factory()->create();
        $parent = PostComment::factory()->for($post)->create(['author_name' => 'Bola', 'status' => CommentStatus::Rejected]);
        $reply = PostComment::factory()->for($post)->pending()->create(['parent_id' => $parent->id]);

        $this->actingAs($this->staff())
            ->patch(route('admin.comments.update', $reply), ['status' => 'approved'])
            ->assertSessionHas('inertia.flash_data.toast.message', 'Approve the comment by Bola first. A reply shows only under an approved comment.');
        $this->assertSame(CommentStatus::Pending, $reply->refresh()->status);
    }

    public function test_the_queue_shows_what_a_reply_answers_and_staff_see_the_waiting_count(): void
    {
        $post = Post::factory()->create();
        $parent = PostComment::factory()->for($post)->create(['author_name' => 'Bola', 'content' => 'Where is the data from?']);
        PostComment::factory()->for($post)->pending()->create(['parent_id' => $parent->id]);

        $this->actingAs($this->staff())->get(route('admin.comments.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pendingComments', 1)
                ->where('comments.data.0.parent.author_name', 'Bola')
                ->where('comments.data.0.parent.excerpt', 'Where is the data from?'));

        // Members are not told about the queue.
        $this->actingAs(User::factory()->create())->get(route('membership.show'))
            ->assertInertia(fn (Assert $page) => $page->where('pendingComments', null));
    }

    public function test_rejected_comments_are_deleted_after_90_days(): void
    {
        $post = Post::factory()->create();
        $old = PostComment::factory()->for($post)->create(['status' => CommentStatus::Rejected]);
        $old->forceFill(['updated_at' => now()->subDays(91)])->saveQuietly();
        $recent = PostComment::factory()->for($post)->create(['status' => CommentStatus::Rejected]);
        $approved = PostComment::factory()->for($post)->create(['status' => CommentStatus::Approved]);
        $approved->forceFill(['updated_at' => now()->subYear()])->saveQuietly();

        $this->artisan('comments:prune-rejected')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertModelExists($approved);
    }
}
