<?php

namespace Tests\Feature\Admin;

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            'content_type' => 'text',
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
            ->assertSessionHasErrors(['title', 'content', 'author_name', 'post_category_id', 'status']);
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

    public function test_staff_moderate_comments(): void
    {
        $post = Post::factory()->create();
        $comment = PostComment::factory()->for($post)->pending()->create(['content' => 'Held remark']);
        $this->actingAs($this->staff());

        $this->patch(route('admin.comments.update', $comment), ['status' => 'approved'])->assertRedirect();
        $this->assertSame(CommentStatus::Approved, $comment->refresh()->status);
        $this->get(route('posts.show', $post->slug))->assertSee('Held remark');

        $reply = PostComment::factory()->for($post)->create(['parent_id' => $comment->id]);
        $this->patch(route('admin.comments.update', $comment), ['status' => 'rejected']);
        $this->assertSame(CommentStatus::Pending, $reply->refresh()->status);

        $this->delete(route('admin.comments.destroy', $comment))->assertRedirect();
        $this->assertSame(0, PostComment::count());
    }
}
