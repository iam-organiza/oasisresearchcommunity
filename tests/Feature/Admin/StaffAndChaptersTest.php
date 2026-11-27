<?php

namespace Tests\Feature\Admin;

use App\Enums\Membership\ApplicationStatus;
use App\Enums\Membership\Grade;
use App\Enums\UserRole;
use App\Models\Membership;
use App\Models\Region;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAndChaptersTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    public function test_every_state_and_the_fct_sit_in_one_of_six_regions(): void
    {
        $this->assertSame(6, Region::count());
        $this->assertSame(37, State::count());

        $this->assertSame(
            ['Benue', 'Federal Capital Territory', 'Kogi', 'Kwara', 'Nasarawa', 'Niger', 'Plateau'],
            Region::query()->where('slug', 'north-central')->firstOrFail()->states->pluck('name')->all(),
        );
    }

    public function test_chapters_count_members_and_applicants_in_progress_by_state(): void
    {
        $lagos = State::query()->where('slug', 'lagos')->firstOrFail();
        Membership::query()->create(['user_id' => User::factory()->create()->id, 'grade' => Grade::Member, 'grade_since' => today(), 'joined_on' => today(), 'state_id' => $lagos->id]);
        foreach ([ApplicationStatus::Offered, ApplicationStatus::Draft, ApplicationStatus::Declined, ApplicationStatus::Approved] as $status) {
            User::factory()->create()->membershipApplications()->create(['state_id' => $lagos->id, 'grade' => Grade::Member])
                ->forceFill(['status' => $status])->save();
        }

        $this->actingAs($this->userWithRole(UserRole::Registrar))->get(route('admin.chapters.index'))
            ->assertInertia(function ($page) use ($lagos) {
                $region = collect($page->toArray()['props']['regions'])->firstWhere('id', $lagos->region_id);
                $state = collect($region['states'])->firstWhere('id', $lagos->id);
                $this->assertSame(1, $state['members']);
                // Only the open offer counts: drafts, declined and approved are not applicants in progress.
                $this->assertSame(1, $state['applicants']);
                $this->assertSame($lagos->id, $region['states'][0]['id'], 'The busiest state comes first.');
            });
    }

    public function test_each_role_opens_only_its_own_areas(): void
    {
        $areas = [
            'content' => route('admin.posts.index'),
            'members' => route('admin.chapters.index'),
            'staff' => route('admin.staff.index'),
        ];

        $allowed = [
            UserRole::Admin->value => ['content', 'members', 'staff'],
            UserRole::Editor->value => ['content'],
            UserRole::Registrar->value => ['members'],
            UserRole::Member->value => [],
        ];

        foreach ($allowed as $role => $areasAllowed) {
            $user = $this->userWithRole(UserRole::from($role));

            foreach ($areas as $area => $url) {
                $this->actingAs($user)->get($url)
                    ->assertStatus(in_array($area, $areasAllowed, true) ? 200 : 403);
            }
        }
    }

    public function test_each_role_lands_on_its_own_area_after_logging_in(): void
    {
        $this->actingAs($this->userWithRole(UserRole::Editor))->get(route('admin'))
            ->assertRedirect(route('admin.posts.index'));

        $this->actingAs($this->userWithRole(UserRole::Registrar))->get(route('admin'))
            ->assertRedirect(route('admin.registrar'));

        $this->actingAs($this->userWithRole(UserRole::Admin))->get(route('admin'))
            ->assertRedirect(route('admin.posts.index'));
    }

    public function test_the_staff_lookup_names_the_account_and_its_role_for_admins_only(): void
    {
        $editor = $this->userWithRole(UserRole::Editor);
        $member = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Obi']);

        $this->actingAs($this->userWithRole(UserRole::Admin))->getJson(route('admin.staff.lookup', ['email' => strtoupper($member->email)]))
            ->assertOk()->assertJson(['account' => ['name' => 'Ada Obi', 'role' => 'member']]);
        $this->actingAs($this->userWithRole(UserRole::Admin))->getJson(route('admin.staff.lookup', ['email' => $editor->email]))
            ->assertJson(['account' => ['role' => 'editor', 'role_label' => 'Editor']]);
        $this->actingAs($this->userWithRole(UserRole::Admin))->getJson(route('admin.staff.lookup', ['email' => 'nobody@example.test']))
            ->assertJson(['account' => null]);
        $this->actingAs($this->userWithRole(UserRole::Registrar))->getJson(route('admin.staff.lookup', ['email' => $member->email]))
            ->assertForbidden();
    }

    public function test_an_admin_gives_a_role_to_an_existing_account(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $member = User::factory()->create(['email' => 'registrar@example.test']);

        $this->actingAs($admin)
            ->post(route('admin.staff.store'), ['email' => 'Registrar@Example.test', 'role' => 'registrar'])
            ->assertSessionHasNoErrors();

        $this->assertSame(UserRole::Registrar, $member->refresh()->role);
    }

    public function test_a_role_needs_an_existing_member_account_and_a_staff_role(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $editor = $this->userWithRole(UserRole::Editor);
        $member = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.staff.store'), ['email' => 'nobody@example.test', 'role' => 'editor'])
            ->assertSessionHasErrors('email');

        $this->actingAs($admin)
            ->post(route('admin.staff.store'), ['email' => $editor->email, 'role' => 'registrar'])
            ->assertSessionHasErrors('email');

        $this->actingAs($admin)
            ->post(route('admin.staff.store'), ['email' => $member->email, 'role' => 'member'])
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRole::Editor, $editor->refresh()->role);
        $this->assertSame(UserRole::Member, $member->refresh()->role);
    }

    public function test_an_admin_changes_or_removes_a_role_but_not_their_own(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $editor = $this->userWithRole(UserRole::Editor);

        $this->actingAs($admin)->patch(route('admin.staff.update', $editor), ['role' => 'registrar'])
            ->assertSessionHasNoErrors();
        $this->assertSame(UserRole::Registrar, $editor->refresh()->role);

        $this->actingAs($admin)->patch(route('admin.staff.update', $editor), ['role' => 'member'])
            ->assertSessionHasNoErrors();
        $this->assertSame(UserRole::Member, $editor->refresh()->role);

        $this->actingAs($admin)->patch(route('admin.staff.update', $admin), ['role' => 'editor'])
            ->assertSessionHasErrors('role');
        $this->assertSame(UserRole::Admin, $admin->refresh()->role);
    }

    public function test_an_admin_can_step_another_admin_down(): void
    {
        // Only admins change roles and nobody changes their own, so an admin always remains.
        $admin = $this->userWithRole(UserRole::Admin);
        $other = $this->userWithRole(UserRole::Admin);

        $this->actingAs($admin)->patch(route('admin.staff.update', $other), ['role' => 'editor'])
            ->assertSessionHasNoErrors();

        $this->assertSame(UserRole::Editor, $other->refresh()->role);
        $this->assertSame(1, User::query()->where('role', UserRole::Admin->value)->count());
    }

    public function test_only_editors_and_admins_can_preview_unpublished_posts(): void
    {
        $this->assertTrue(UserRole::Editor->canManageContent());
        $this->assertTrue(UserRole::Admin->canManageContent());
        $this->assertFalse(UserRole::Registrar->canManageContent());
    }
}
