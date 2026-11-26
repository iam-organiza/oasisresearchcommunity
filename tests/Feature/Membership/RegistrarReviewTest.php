<?php

namespace Tests\Feature\Membership;

use App\Enums\Membership\ApplicationStatus;
use App\Enums\Membership\Grade;
use App\Enums\Membership\Route;
use App\Enums\UserRole;
use App\Models\Membership;
use App\Models\MembershipApplication;
use App\Models\State;
use App\Models\User;
use App\Notifications\Membership\GradeOffered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrarReviewTest extends TestCase
{
    use RefreshDatabase;

    private function registrar(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::Registrar])->save();

        return $user;
    }

    /**
     * A submitted application at a grade, ready for the registrar.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function submitted(Grade $grade = Grade::Member, array $overrides = []): MembershipApplication
    {
        $application = User::factory()->create()->membershipApplications()->create([
            'phone' => '08030000000',
            'state_id' => State::query()->where('slug', 'nasarawa')->value('id'),
            'city' => 'Lafia',
            'grade' => $grade,
            'highest_qualification' => 'masters',
            'current_role' => 'Lecturer II',
            'institution' => 'Federal University Lafia',
            'years_experience' => 3,
            'claimed_areas' => ['methodology'],
            'route' => Route::Exam,
            'exam_status' => 'will_take',
            'induction_event' => 'conference',
            'induction_status' => 'will_attend',
            ...$overrides,
        ]);
        $application->forceFill(['status' => ApplicationStatus::Submitted, 'submitted_at' => now()])->save();

        return $application;
    }

    /**
     * An application whose offer was accepted, by default on a route with no
     * exam (Certification Programme), so the Registrar can approve it.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function accepted(Grade $grade = Grade::Member, array $overrides = []): MembershipApplication
    {
        $application = $this->submitted($grade, ['route' => Route::CertificationProgramme, 'exam_status' => null, ...$overrides]);
        $application->forceFill(['status' => ApplicationStatus::Accepted, 'offered_grade' => $grade, 'offered_at' => now(), 'accepted_at' => now()])->save();

        return $application;
    }

    public function test_only_membership_staff_open_the_registrar_pages(): void
    {
        $application = $this->submitted();
        $editor = User::factory()->create();
        $editor->forceFill(['role' => UserRole::Editor])->save();

        foreach ([route('admin.registrar'), route('admin.applications.index'), route('admin.applications.show', $application), route('admin.members.index')] as $url) {
            $this->actingAs($this->registrar())->get($url)->assertOk();
            $this->actingAs($editor)->get($url)->assertForbidden();
        }
    }

    public function test_approving_puts_the_applicant_on_the_register_with_the_next_number(): void
    {
        $registrar = $this->registrar();
        $year = now()->year;

        $first = $this->accepted(Grade::Member);
        $second = $this->accepted(Grade::Member);
        $fellow = $this->accepted(Grade::Fellow);

        foreach ([$first, $second, $fellow] as $application) {
            $this->actingAs($registrar)->post(route('admin.applications.decide', $application), ['action' => 'approve'])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame("OASIS/MEM/{$year}/0001", $first->user->membership->number);
        $this->assertSame("OASIS/MEM/{$year}/0002", $second->user->membership->number);
        $this->assertSame("OASIS/FEL/{$year}/0001", $fellow->user->membership->number);

        $first->refresh();
        $this->assertSame(ApplicationStatus::Approved, $first->status);
        $this->assertSame('approved', $first->events()->reorder('id', 'desc')->value('action'));
        $this->assertFalse($first->user->membership->isActive());

        // Approved applications take no more decisions.
        $this->actingAs($registrar)->post(route('admin.applications.decide', $first), ['action' => 'approve'])->assertForbidden();

        // Someone still to take the website's exam is approved after the exam, not now.
        $exam = $this->accepted(Grade::Member, ['route' => Route::Exam, 'exam_status' => 'will_take']);
        $this->actingAs($registrar)->post(route('admin.applications.decide', $exam), ['action' => 'approve'])->assertSessionHasErrors('action');
    }

    public function test_a_waived_fee_makes_the_new_member_active_at_once(): void
    {
        $registrar = $this->registrar();
        $application = $this->accepted(Grade::Student);

        $this->actingAs($registrar)->patch(route('admin.applications.fee-waiver', $application), ['fee_waived' => true]);
        $this->actingAs($registrar)->post(route('admin.applications.decide', $application), ['action' => 'approve']);

        $membership = $application->user->membership;
        $this->assertTrue($membership->isActive());
        $charge = $application->user->charges()->where('kind', 'induction')->sole();
        $this->assertSame(50000, $charge->amount);
        $this->assertSame(50000, $charge->waived_amount);
        $this->assertSame('Waived', $charge->status());

        // Once they are on the register, waivers are made on their account.
        $this->actingAs($registrar)->patch(route('admin.applications.fee-waiver', $application), ['fee_waived' => false])
            ->assertSessionHasErrors('fee_waived');
    }

    public function test_an_exemption_must_be_decided_before_approval_and_a_refusal_means_the_exam(): void
    {
        $registrar = $this->registrar();
        $application = $this->accepted(Grade::Fellow, [
            'route' => Route::Exemption,
            'exemption_reason' => str_repeat('Twenty years of published policy research. ', 3),
            'exemption_links' => ['https://scholar.google.com/citations?user=x'],
        ]);
        $application->forceFill(['exemption_status' => 'requested'])->save();

        $this->actingAs($registrar)->post(route('admin.applications.decide', $application), ['action' => 'approve'])
            ->assertSessionHasErrors('action');

        $this->actingAs($registrar)->post(route('admin.applications.exemption', $application), ['decision' => 'refused'])
            ->assertSessionHasErrors('note');

        $this->actingAs($registrar)->post(route('admin.applications.exemption', $application), ['decision' => 'refused', 'note' => 'Please take the exam.'])
            ->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame('refused', $application->exemption_status);
        $this->assertSame(Route::Exam, $application->route);
        $this->assertSame('will_take', $application->exam_status);
        // They had accepted their offer, so the exam fee is now owed.
        $this->assertSame(20000, $application->user->charges()->where('kind', 'exam')->sole()->amount);
    }

    public function test_the_registrar_corrects_an_offer_and_the_applicant_accepts_it(): void
    {
        Notification::fake();
        $registrar = $this->registrar();
        $application = $this->submitted(Grade::Fellow);
        $application->forceFill(['status' => ApplicationStatus::Offered, 'offered_grade' => Grade::Graduate, 'flags' => ['other_evidence']])->save();

        $this->actingAs($registrar)->post(route('admin.applications.decide', $application), ['action' => 'correct'])
            ->assertSessionHasErrors(['grade', 'reason']);

        $this->actingAs($registrar)->post(route('admin.applications.decide', $application), [
            'action' => 'correct', 'grade' => 'member', 'reason' => 'Your CV shows two published articles and reviewing for a journal, so we offer Member.',
        ])->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame(ApplicationStatus::Offered, $application->status);
        $this->assertSame(Grade::Member, $application->offered_grade);
        $this->assertNotNull($application->reviewed_at);
        Notification::assertSentTo($application->user, GradeOffered::class, fn (GradeOffered $n) => $n->corrected);

        $this->actingAs($application->user->fresh())->post(route('membership.accept'))->assertRedirect(route('membership.show'));

        $application->refresh();
        $this->assertSame(ApplicationStatus::Accepted, $application->status);
        $this->assertSame('offer_accepted', $application->events()->reorder('id', 'desc')->value('action'));
        // On the exam route, accepting puts the exam fee on their account; they join the register after the exam.
        $this->assertSame(20000, $application->user->charges()->where('kind', 'exam')->sole()->amount);
        $this->assertNull($application->user->refresh()->membership);
        $this->actingAs($application->user->fresh())->post(route('membership.accept'))->assertForbidden();
    }

    public function test_an_applicant_can_appeal_a_lower_offer_with_reasons_and_evidence(): void
    {
        Storage::fake('local');
        Notification::fake();
        $registrar = $this->registrar();
        $application = $this->submitted(Grade::Fellow);
        $application->forceFill(['status' => ApplicationStatus::Offered, 'offered_grade' => Grade::Member])->save();

        $owner = $application->user;
        $this->actingAs($owner)->post(route('membership.appeal'), ['appeal_reason' => 'Too short'])
            ->assertSessionHasErrors('appeal_reason');

        $this->actingAs($owner->fresh())->post(route('membership.appeal'), [
            'appeal_reason' => 'I have reviewed for three journals since 2020; the letters are attached.',
            'files' => [UploadedFile::fake()->create('reviewer-letters.pdf', 200, 'application/pdf')],
        ])->assertRedirect(route('membership.show'));

        $application->refresh();
        $this->assertSame(ApplicationStatus::Submitted, $application->status);
        $this->assertSame(1, $application->documents()->count());
        $this->assertSame('appealed', $application->events()->reorder('id', 'desc')->value('action'));

        // The Registrar answers the appeal with a new offer.
        $this->actingAs($registrar)->post(route('admin.applications.decide', $application), [
            'action' => 'correct', 'grade' => 'fellow', 'reason' => 'The reviewing letters meet the Fellow criteria.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(Grade::Fellow, $application->refresh()->offered_grade);
        $this->assertSame(ApplicationStatus::Offered, $application->status);

        // An offer of the grade asked for cannot be appealed.
        $this->actingAs($owner->fresh())->post(route('membership.appeal'), ['appeal_reason' => str_repeat('More reasons. ', 5)])->assertForbidden();
    }

    public function test_more_information_reopens_the_application_and_a_resubmission_is_logged(): void
    {
        $registrar = $this->registrar();
        $application = $this->submitted();
        foreach (['about', 'grade', 'experience', 'route'] as $step) {
            $application->markComplete($step);
        }
        $application->save();
        $application->documents()->create(['kind' => 'cv', 'path' => 'x.pdf', 'original_name' => 'cv.pdf', 'mime_type' => 'application/pdf', 'size' => 1]);

        $this->actingAs($registrar)->post(route('admin.applications.decide', $application), ['action' => 'more_information'])
            ->assertSessionHasErrors('message');
        $this->actingAs($registrar)->post(route('admin.applications.decide', $application), [
            'action' => 'more_information', 'message' => 'Please upload your MSc certificate.',
        ])->assertSessionHasNoErrors();

        $owner = $application->user;
        $this->actingAs($owner)->put(route('membership.apply.update', 'about'), [
            'phone' => '08031111111', 'state_id' => $application->state_id, 'city' => 'Keffi',
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('membership.apply.submit'), ['declaration' => true])->assertRedirect(route('membership.show'));

        $application->refresh();
        // Sent back, the application is offered a grade again.
        $this->assertSame(ApplicationStatus::Offered, $application->status);
        $this->assertSame('Keffi', $application->city);
        $this->assertSame(['resubmitted', 'offered'], $application->events()->reorder('id', 'desc')->limit(2)->pluck('action')->reverse()->values()->all());
    }

    public function test_a_declined_applicant_sees_why_and_can_apply_again(): void
    {
        $registrar = $this->registrar();
        $application = $this->submitted();

        $this->actingAs($registrar)->post(route('admin.applications.decide', $application), [
            'action' => 'decline', 'reason' => 'No evidence of research experience.', 'improvements' => 'Apply as a Graduate Member, or add evidence of 2 years of research.',
        ])->assertSessionHasNoErrors();

        $owner = $application->user;
        $this->actingAs($owner)->get(route('membership.show'))
            ->assertInertia(fn ($page) => $page
                ->where('application.status', 'declined')
                ->where('application.decision_improvements', 'Apply as a Graduate Member, or add evidence of 2 years of research.'));

        $this->actingAs($owner)->post(route('membership.apply.start'))->assertRedirect(route('membership.apply.edit', 'about'));
        $this->assertSame(2, $owner->membershipApplications()->count());
    }

    public function test_the_registrar_records_existing_members_and_their_fees(): void
    {
        $registrar = $this->registrar();
        $member = User::factory()->create(['email' => 'founding@example.test']);

        $this->actingAs($registrar)->post(route('admin.members.store'), [
            'email' => 'founding@example.test', 'grade' => 'fellow', 'joined_on' => '2023-05-10', 'number' => 'FEL-1',
            'induction' => 'owed', 'induction_amount' => 125000,
        ])->assertSessionHasErrors('number');

        $this->actingAs($registrar)->post(route('admin.members.store'), [
            'email' => 'founding@example.test', 'grade' => 'fellow', 'joined_on' => '2023-05-10',
            'induction' => 'owed', 'induction_amount' => 125000,
        ])->assertRedirect(route('admin.members.index'));

        $membership = $member->refresh()->membership;
        $this->assertSame('OASIS/FEL/2023/0001', $membership->number);
        $this->assertFalse($membership->isActive());
        // They owe the fee of their day, not today's.
        $this->assertSame(125000, $member->charges()->where('kind', 'induction')->sole()->balance());

        $this->actingAs($registrar)->post(route('admin.members.store'), [
            'email' => 'founding@example.test', 'grade' => 'member', 'joined_on' => '2024-01-01',
            'induction' => 'owed', 'induction_amount' => 150000,
        ])->assertSessionHasErrors('email');

        // Someone whose fee was paid before the website is active at once, and gets no receipt email.
        Notification::fake();
        $paid = User::factory()->create(['email' => 'paid@example.test']);
        $this->actingAs($registrar)->post(route('admin.members.store'), [
            'email' => 'paid@example.test', 'grade' => 'member', 'joined_on' => '2024-03-01',
            'induction' => 'paid', 'induction_amount' => 55000,
        ])->assertSessionHasNoErrors();
        $this->assertTrue($paid->refresh()->membership->isActive());
        $this->assertSame('Received before the website', $paid->charges()->where('kind', 'induction')->sole()->payments()->sole()->method->label());
        Notification::assertNothingSent();

        // Someone on the register has nothing to apply for.
        $this->actingAs($member)->post(route('membership.apply.start'))->assertRedirect(route('membership.show'));
        $this->assertSame(0, $member->membershipApplications()->count());
    }

    public function test_students_cannot_claim_the_advanced_areas(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student)->post(route('membership.apply.start'));
        // Each request gets a fresh copy of the user, as it would in the browser.
        $this->actingAs($student->fresh())->put(route('membership.apply.update', 'grade'), ['grade' => 'student'])
            ->assertRedirect(route('membership.apply.edit', 'experience'));

        $this->actingAs($student->fresh())->put(route('membership.apply.update', 'experience'), [
            'highest_qualification' => 'secondary', 'current_role' => 'Final-year student', 'role_type' => 'student', 'currently_studying' => 'undergraduate',
            'institution' => 'Nasarawa State University',
            'years_experience' => 0, 'claimed_areas' => ['literature_review', 'supervision', 'consulting'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['literature_review'], $student->refresh()->latestApplication->claimed_areas);
    }

    public function test_membership_numbers_count_up_per_grade_and_year(): void
    {
        Membership::query()->create([
            'user_id' => User::factory()->create()->id, 'number' => 'OASIS/MEM/2026/0009',
            'grade' => Grade::Member, 'joined_on' => '2026-01-01',
        ]);

        $this->assertSame('OASIS/MEM/2026/0010', Membership::nextNumber(Grade::Member, 2026));
        $this->assertSame('OASIS/MEM/2027/0001', Membership::nextNumber(Grade::Member, 2027));
        $this->assertSame('OASIS/STU/2026/0001', Membership::nextNumber(Grade::Student, 2026));
    }
}
