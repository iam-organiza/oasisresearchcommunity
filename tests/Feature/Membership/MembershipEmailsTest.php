<?php

namespace Tests\Feature\Membership;

use App\Enums\Membership\ApplicationStatus;
use App\Enums\Membership\Grade;
use App\Enums\Membership\Route;
use App\Enums\UserRole;
use App\Models\MembershipApplication;
use App\Models\State;
use App\Models\User;
use App\Notifications\Membership\ApplicationApproved;
use App\Notifications\Membership\ApplicationDeclined;
use App\Notifications\Membership\ApplicationReceived;
use App\Notifications\Membership\ApplicationWaiting;
use App\Notifications\Membership\ExemptionRefused;
use App\Notifications\Membership\GradeOffered;
use App\Notifications\Membership\MoreInformationNeeded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MembershipEmailsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    /**
     * An application with every step done, ready to submit.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function readyToSubmit(array $overrides = []): MembershipApplication
    {
        $application = User::factory()->create()->membershipApplications()->create([
            'phone' => '08030000000',
            'state_id' => State::query()->value('id'),
            'city' => 'Lafia',
            'grade' => Grade::Member,
            'highest_qualification' => 'masters',
            'current_role' => 'Lecturer II',
            'role_type' => 'lecturer',
            'currently_studying' => 'no',
            'institution' => 'Federal University Lafia',
            'years_experience' => 3,
            'claimed_areas' => ['methodology'],
            'route' => Route::Exam,
            'exam_status' => 'will_take',
            'induction_event' => 'conference',
            'induction_status' => 'will_attend',
            ...$overrides,
        ]);
        foreach (['about', 'grade', 'experience', 'route'] as $step) {
            $application->markComplete($step);
        }
        $application->save();
        $application->documents()->create(['kind' => 'cv', 'path' => 'x.pdf', 'original_name' => 'cv.pdf', 'mime_type' => 'application/pdf', 'size' => 1]);

        return $application;
    }

    private function submitted(): MembershipApplication
    {
        $application = $this->readyToSubmit();
        $application->forceFill(['status' => ApplicationStatus::Submitted, 'submitted_at' => now()])->save();

        return $application;
    }

    /** Accepted on the Certification Programme route: no exam, ready to approve. */
    private function accepted(): MembershipApplication
    {
        $application = $this->readyToSubmit(['route' => Route::CertificationProgramme, 'exam_status' => null]);
        $application->forceFill(['status' => ApplicationStatus::Accepted, 'offered_grade' => Grade::Member, 'submitted_at' => now(), 'accepted_at' => now()])->save();

        return $application;
    }

    public function test_submitting_emails_the_grade_offer_and_only_flagged_ones_reach_the_registrar(): void
    {
        Notification::fake();
        $registrar = $this->userWithRole(UserRole::Registrar);
        $application = $this->readyToSubmit();

        $this->actingAs($application->user)->post(route('membership.apply.submit'), ['declaration' => true])
            ->assertRedirect(route('membership.show'));

        // A master's, 3 years and no publications or supervising: Graduate Member, not the Member asked for.
        Notification::assertSentTo($application->user, GradeOffered::class, fn (GradeOffered $n) => ! $n->corrected);
        $this->assertSame(Grade::Graduate, $application->refresh()->offered_grade);
        Notification::assertNotSentTo($registrar, ApplicationWaiting::class);
        Notification::assertNothingSentTo(new AnonymousNotifiable);

        // Anything else relevant is for a person to read.
        $flagged = $this->readyToSubmit(['other_evidence' => 'Led the 2024 Nasarawa malaria survey.']);
        $this->actingAs($flagged->user)->post(route('membership.apply.submit'), ['declaration' => true]);
        Notification::assertSentTo($registrar, ApplicationWaiting::class);
    }

    public function test_admins_hear_about_flagged_applications_while_there_is_no_registrar(): void
    {
        Notification::fake();
        $admin = $this->userWithRole(UserRole::Admin);
        $application = $this->readyToSubmit(['route' => Route::Exemption, 'exam_status' => null, 'exemption_reason' => str_repeat('Long public record. ', 5), 'exemption_links' => ['https://example.test']]);

        $this->actingAs($application->user)->post(route('membership.apply.submit'), ['declaration' => true]);

        Notification::assertSentTo($admin, ApplicationWaiting::class);
        $this->assertSame('requested', $application->refresh()->exemption_status);
    }

    public function test_each_decision_emails_the_applicant(): void
    {
        Notification::fake();
        $registrar = $this->userWithRole(UserRole::Registrar);

        $approve = $this->accepted();
        $this->actingAs($registrar)->post(route('admin.applications.decide', $approve), ['action' => 'approve']);
        Notification::assertSentTo($approve->user, ApplicationApproved::class);

        $correct = $this->submitted();
        $this->actingAs($registrar)->post(route('admin.applications.decide', $correct), [
            'action' => 'correct', 'grade' => 'graduate', 'reason' => 'No publications are listed yet.',
        ]);
        Notification::assertSentTo($correct->user, GradeOffered::class, fn (GradeOffered $n) => $n->corrected);

        $more = $this->submitted();
        $this->actingAs($registrar)->post(route('admin.applications.decide', $more), [
            'action' => 'more_information', 'message' => 'Please upload your MSc certificate.',
        ]);
        Notification::assertSentTo($more->user, MoreInformationNeeded::class);

        $decline = $this->submitted();
        $this->actingAs($registrar)->post(route('admin.applications.decide', $decline), [
            'action' => 'decline', 'reason' => 'No research experience yet.', 'improvements' => 'Apply as a Graduate Member.',
        ]);
        Notification::assertSentTo($decline->user, ApplicationDeclined::class);
    }

    public function test_appealing_and_a_refused_exemption_email_the_right_people(): void
    {
        Notification::fake();
        $registrar = $this->userWithRole(UserRole::Registrar);

        $appealing = $this->readyToSubmit(['grade' => Grade::Fellow]);
        $appealing->forceFill(['status' => ApplicationStatus::Offered, 'offered_grade' => Grade::Graduate])->save();
        $this->actingAs($appealing->user)->post(route('membership.appeal'), [
            'appeal_reason' => 'Two of my articles were published last month; links are in my CV.',
        ]);
        Notification::assertSentTo($appealing->user, ApplicationReceived::class, fn ($n) => $n->kind === 'appealed');
        Notification::assertSentTo($registrar, ApplicationWaiting::class, fn ($n) => $n->kind === 'appealed');

        $exempt = $this->readyToSubmit(['route' => Route::Exemption, 'exam_status' => null]);
        $exempt->forceFill(['status' => ApplicationStatus::Offered, 'offered_grade' => Grade::Member, 'exemption_status' => 'requested'])->save();
        $this->actingAs($registrar)->post(route('admin.applications.exemption', $exempt), ['decision' => 'refused', 'note' => 'Please take the exam.']);
        Notification::assertSentTo($exempt->user, ExemptionRefused::class, fn ($n) => $n->note === 'Please take the exam.');
    }

    public function test_a_failing_mail_server_does_not_undo_a_decision(): void
    {
        $registrar = $this->userWithRole(UserRole::Registrar);
        $application = $this->accepted();
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP is down'));

        $this->actingAs($registrar)->post(route('admin.applications.decide', $application), ['action' => 'approve'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::Approved, $application->refresh()->status);
        $this->assertNotNull($application->user->membership);
    }

    public function test_the_referee_answers_from_the_signed_link_only(): void
    {
        $application = $this->submitted();
        $application->forceFill(['referee_name' => 'Prof. A. Bello', 'referee_email' => 'bello@example.test', 'referee_relationship' => 'Head of department'])->save();

        $this->get(route('referee.show', $application))->assertForbidden();

        $page = URL::temporarySignedRoute('referee.show', now()->addDay(), ['application' => $application->id]);
        $this->get($page)->assertOk()->assertSee('Can you vouch for')->assertSee('their character and their work in research');

        $answer = URL::temporarySignedRoute('referee.answer', now()->addHour(), ['application' => $application->id]);
        $this->post($answer, ['answer' => 'confirmed'])->assertRedirect();

        $application->refresh();
        $this->assertSame('confirmed', $application->referee_status);
        $this->assertNotNull($application->referee_responded_at);
        $this->assertSame('referee_confirmed', $application->events()->reorder('id', 'desc')->value('action'));
        $this->get($page)->assertSee('Thank you, Prof. A. Bello')->assertSee('There is nothing more to do');
    }

    public function test_a_refused_exemption_says_the_next_step_for_where_they_are(): void
    {
        $application = $this->submitted();
        $application->forceFill(['status' => ApplicationStatus::Offered])->save();
        $before = (string) (new ExemptionRefused('Take the exam.', $application))->toMail($application->user)->render();

        $this->assertStringContainsString('Take the exam.', $before);
        $this->assertStringContainsString('Accept your grade offer and pay the exam fee of ₦20,000', $before);

        $application->forceFill(['status' => ApplicationStatus::Accepted])->save();
        $after = (string) (new ExemptionRefused('Take the exam.', $application))->toMail($application->user)->render();

        $this->assertStringContainsString('Pay the exam fee of ₦20,000', $after);
        $this->assertStringNotContainsString('Accept your grade offer', $after);
    }

    public function test_the_offer_email_says_why_and_what_comes_next(): void
    {
        $application = $this->submitted();
        $application->forceFill([
            'status' => ApplicationStatus::Offered,
            'offered_grade' => Grade::Graduate,
            'offer_reasons' => ['For Member we look for a publication, or experience supervising, editing or reviewing.'],
        ])->save();

        $html = (string) (new GradeOffered($application))->toMail($application->user)->render();

        $this->assertStringContainsString('From your answers, we offer you a grade', $html);
        $this->assertMatchesRegularExpression('/Offered<\/p>\s*<p[^>]*>Graduate Member</', $html);
        $this->assertStringContainsString('Why not Member', $html);
        $this->assertStringContainsString('For Member we look for a publication', $html);
        $this->assertStringContainsString('you can appeal to the Registrar', $html);
        $this->assertStringContainsString('pay the exam fee of ₦20,000', $html);
        $this->assertStringContainsString(route('membership.show'), $html);
    }

    public function test_the_fees_are_oasis_figures(): void
    {
        $this->assertSame(
            [250000, 150000, 100000, 50000],
            array_map(fn (Grade $grade) => $grade->inductionFee(), Grade::cases()),
        );
        $this->assertSame(
            [60000, 50000, 40000, 20000],
            array_map(fn (Grade $grade) => $grade->annualDues(), Grade::cases()),
        );
        $this->assertSame(20000, config('membership.exam_fee'));
    }
}
