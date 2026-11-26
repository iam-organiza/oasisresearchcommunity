<?php

namespace Tests\Feature\Membership;

use App\Enums\Membership\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\MembershipApplication;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicationTest extends TestCase
{
    use RefreshDatabase;

    private function startApplication(User $user): MembershipApplication
    {
        $this->actingAs($user)->post(route('membership.apply.start'))
            ->assertRedirect(route('membership.apply.edit', 'about'));

        return $user->refresh()->latestApplication;
    }

    /**
     * Fill in every step before Review with valid answers.
     */
    private function completeSteps(User $user): MembershipApplication
    {
        Storage::fake('local');
        $application = $this->startApplication($user);
        $state = State::query()->where('slug', 'federal-capital-territory')->firstOrFail();

        $this->put(route('membership.apply.update', 'about'), [
            'title' => 'Dr.', 'phone' => '+234 803 000 0000', 'state_id' => $state->id, 'city' => 'Abuja',
        ])->assertSessionHasNoErrors();
        $this->put(route('membership.apply.update', 'grade'), ['grade' => 'member'])->assertSessionHasNoErrors();
        $this->put(route('membership.apply.update', 'experience'), [
            'highest_qualification' => 'masters', 'current_role' => 'Lecturer II', 'institution' => 'University of Abuja',
            'years_experience' => 4, 'publications' => [], 'competencies' => [],
        ])->assertSessionHasNoErrors();
        $this->put(route('membership.apply.update', 'route'), [
            'route' => 'direct', 'induction_event' => 'conference', 'induction_status' => 'will_attend',
        ])->assertSessionHasNoErrors();
        $this->post(route('membership.documents.store'), [
            'kind' => 'cv', 'file' => UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->put(route('membership.apply.update', 'referee'), [])->assertSessionHasNoErrors();

        return $application->refresh();
    }

    public function test_a_member_without_an_application_is_offered_the_two_paths(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('membership.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('membership/show')->where('application', null));
    }

    public function test_members_land_on_their_membership_page_right_after_verifying(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin', ['verified' => 1]))
            ->assertRedirect(route('membership.show'));
    }

    public function test_an_unverified_account_cannot_apply(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('membership.show'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_starting_twice_carries_on_with_the_same_application(): void
    {
        $user = User::factory()->create();
        $this->startApplication($user);
        $this->startApplication($user);

        $this->assertSame(1, $user->membershipApplications()->count());
    }

    public function test_each_step_checks_its_answers_and_moves_on(): void
    {
        $user = User::factory()->create();
        $application = $this->startApplication($user);

        $this->put(route('membership.apply.update', 'about'), ['phone' => ''])
            ->assertSessionHasErrors(['phone', 'state_id', 'city']);
        $this->assertFalse($application->refresh()->isComplete('about'));

        $this->put(route('membership.apply.update', 'about'), [
            'phone' => '08030000000', 'state_id' => State::query()->value('id'), 'city' => 'Lafia',
        ])->assertRedirect(route('membership.apply.edit', 'grade'));
        $this->assertTrue($application->refresh()->isComplete('about'));

        // "Save for later" keeps the person on the same step.
        $this->put(route('membership.apply.update', 'grade'), ['grade' => 'student', 'stay' => true])
            ->assertRedirect(route('membership.apply.edit', 'grade'));
    }

    public function test_fellow_needs_its_basis_and_evidence_which_other_grades_clear(): void
    {
        $user = User::factory()->create();
        $application = $this->startApplication($user);

        $this->put(route('membership.apply.update', 'grade'), ['grade' => 'fellow'])
            ->assertSessionHasErrors(['fellow_basis', 'grade_evidence']);

        $this->put(route('membership.apply.update', 'grade'), [
            'grade' => 'fellow', 'fellow_basis' => 'leader', 'grade_evidence' => 'Member of the House of Representatives, 2019 to 2023.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('leader', $application->refresh()->fellow_basis->value);

        $this->put(route('membership.apply.update', 'grade'), ['grade' => 'member', 'fellow_basis' => 'leader'])
            ->assertSessionHasNoErrors();
        $this->assertNull($application->refresh()->fellow_basis);
        $this->assertNull($application->grade_evidence);
    }

    public function test_publications_are_replaced_on_each_save_and_blank_ratings_dropped(): void
    {
        $user = User::factory()->create();
        $application = $this->startApplication($user);
        $base = ['highest_qualification' => 'phd', 'current_role' => 'Senior Lecturer', 'institution' => 'NSUK', 'years_experience' => 11];

        $this->put(route('membership.apply.update', 'experience'), [...$base, 'publications' => [
            ['title' => 'One', 'venue' => 'OASIS Journal', 'year' => 2020, 'link' => 'https://doi.org/10.1/x'],
            ['title' => 'Two', 'venue' => 'OASIS Journal', 'year' => 2021, 'link' => ''],
        ], 'competencies' => ['ethics' => 5, 'data' => null, 'invented' => 3]])->assertSessionHasNoErrors();

        $this->assertSame(['One', 'Two'], $application->refresh()->publications->pluck('title')->all());
        $this->assertSame(['ethics' => 5], $application->competencies);

        $this->put(route('membership.apply.update', 'experience'), [...$base, 'publications' => [
            ['title' => 'Three', 'venue' => 'Book', 'year' => 2022],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(['Three'], $application->refresh()->publications->pluck('title')->all());

        $this->put(route('membership.apply.update', 'experience'), [...$base, 'publications' => [
            ['title' => '', 'venue' => 'Book', 'year' => 2999, 'link' => 'not a link'],
        ]])->assertSessionHasErrors(['publications.0.title', 'publications.0.year', 'publications.0.link']);
    }

    public function test_the_exam_route_and_attended_induction_need_their_details(): void
    {
        $user = User::factory()->create();
        $this->startApplication($user);

        $this->put(route('membership.apply.update', 'route'), [
            'route' => 'exam', 'induction_event' => 'forum', 'induction_status' => 'attended',
        ])->assertSessionHasErrors(['exam_status', 'induction_attended_on']);

        $this->put(route('membership.apply.update', 'route'), [
            'route' => 'exam', 'exam_status' => 'passed', 'induction_event' => 'forum', 'induction_status' => 'will_attend',
        ])->assertSessionHasErrors(['exam_passed_on']);
    }

    public function test_documents_are_private_to_the_applicant_and_membership_staff(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $application = $this->startApplication($user);

        $this->post(route('membership.documents.store'), [
            'kind' => 'cv', 'file' => UploadedFile::fake()->create('my-cv.pdf', 300, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $document = $application->documents()->firstOrFail();
        Storage::disk('local')->assertExists($document->path);
        $this->assertStringStartsWith("applications/{$application->id}/", $document->path);
        $this->assertTrue($application->refresh()->isComplete('documents'));

        $this->get(route('membership.documents.show', $document))->assertOk();

        $this->actingAs(User::factory()->create())->get(route('membership.documents.show', $document))->assertNotFound();

        $editor = User::factory()->create();
        $editor->forceFill(['role' => UserRole::Editor])->save();
        $this->actingAs($editor)->get(route('membership.documents.show', $document))->assertNotFound();

        $registrar = User::factory()->create();
        $registrar->forceFill(['role' => UserRole::Registrar])->save();
        $this->actingAs($registrar)->get(route('membership.documents.show', $document))->assertOk();

        $this->actingAs($user)->delete(route('membership.documents.destroy', $document))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($document->path);
    }

    public function test_only_pdf_and_image_files_up_to_five_megabytes_are_accepted(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->startApplication($user);

        $this->post(route('membership.documents.store'), [
            'kind' => 'cv', 'file' => UploadedFile::fake()->create('cv.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertSessionHasErrors('file');

        $this->post(route('membership.documents.store'), [
            'kind' => 'certificate', 'file' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf'),
        ])->assertSessionHasErrors('file');
    }

    public function test_submitting_needs_every_step_and_the_declaration_then_locks_the_answers(): void
    {
        $user = User::factory()->create();
        $application = $this->startApplication($user);

        $this->post(route('membership.apply.submit'), ['declaration' => true])->assertSessionHasErrors('declaration');

        $application = $this->completeSteps(User::factory()->create());
        $owner = $application->user;

        $this->actingAs($owner)->post(route('membership.apply.submit'), [])->assertSessionHasErrors('declaration');

        $this->actingAs($owner)->post(route('membership.apply.submit'), [
            'declaration' => true, 'directory_consent' => true,
        ])->assertRedirect(route('membership.show'));

        $application->refresh();
        $this->assertSame(ApplicationStatus::Submitted, $application->status);
        $this->assertNotNull($application->submitted_at);
        $this->assertNotNull($application->declared_at);
        $this->assertTrue($application->directory_consent);
        $this->assertFalse($application->keep_documents_two_years);

        // After submitting, the answers can no longer change.
        $this->actingAs($owner)->put(route('membership.apply.update', 'about'), ['phone' => '0'])->assertForbidden();
        $this->actingAs($owner)->get(route('membership.apply.edit', 'about'))->assertRedirect(route('membership.show'));
        $this->actingAs($owner)->post(route('membership.apply.start'))->assertRedirect(route('membership.show'));
    }

    public function test_the_landing_page_lists_the_four_grades_and_institutional_membership(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder(['Student Members', 'Graduate Members', 'Members', 'Fellows', 'Institutional Members'])
            ->assertDontSee('Associate Members')
            ->assertDontSee('Senior Members');
    }
}
