<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\FeaturedMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeaturedMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::Admin])->save();

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'title' => 'Dr.',
            'first_name' => 'Christy',
            'last_name' => 'Zwingina',
            'position' => 'Fellow',
            'job_description' => "Dean, Faculty of Administration\nBingham University, Karu",
        ];
    }

    public function test_members_cannot_manage_featured_members(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.featured-members.index'))->assertForbidden();
        $this->post(route('admin.featured-members.store'), $this->payload())->assertForbidden();
        $this->assertSame(0, FeaturedMember::count());
    }

    public function test_staff_can_add_a_featured_member_who_then_shows_on_the_landing_page(): void
    {
        $this->actingAs($this->staff());
        $this->get(route('admin.featured-members.index'))->assertOk();
        $this->get(route('admin.featured-members.create'))->assertOk();

        $this->post(route('admin.featured-members.store'), $this->payload() + [
            'avatar_file' => UploadedFile::fake()->image('christy.jpg'),
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.featured-members.index'));

        $member = FeaturedMember::firstOrFail();
        $this->assertSame('Dr. Christy Zwingina', $member->full_name);
        $this->assertStringStartsWith('assets/media/avatars/avatar_', (string) $member->avatar);
        Storage::disk('media')->assertExists(str_replace('assets/media/', '', (string) $member->avatar));
        $this->get(route('home'))->assertSee('Dr. Christy Zwingina');
    }

    public function test_a_new_featured_member_needs_a_name_grade_and_photo(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.featured-members.store'), [])
            ->assertSessionHasErrors(['first_name', 'last_name', 'position', 'avatar_file']);
    }

    public function test_staff_can_edit_without_replacing_the_photo_and_remove_a_member(): void
    {
        $member = FeaturedMember::create($this->payload() + ['avatar' => 'assets/media/avatars/old.jpg']);
        $this->actingAs($this->staff());

        $this->get(route('admin.featured-members.edit', $member))->assertOk();
        $this->put(route('admin.featured-members.update', $member), ['position' => 'Senior Member'] + $this->payload())
            ->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertSame('Senior Member', $member->position);
        $this->assertSame('assets/media/avatars/old.jpg', $member->avatar);

        $this->delete(route('admin.featured-members.destroy', $member))->assertRedirect();
        $this->assertSame(0, FeaturedMember::count());
    }
}
