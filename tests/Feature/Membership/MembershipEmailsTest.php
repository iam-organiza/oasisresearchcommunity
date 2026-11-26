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
use App\Notifications\Membership\GradeRecommended;
use App\Notifications\Membership\MoreInformationNeeded;
use App\Notifications\Membership\RefereeRequest;
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
            'institution' => 'Federal University Lafia',
            'years_experience' => 3,
            'claimed_areas' => ['methodology'],
            'route' => Route::Exam,
            'exam_status' => 'will_take',
            'induction_event' => 'conference',
            'induction_status' => 'will_attend',
            ...$overrides,
        ]);
        foreach (['about', 'grade', 'experience', 'route', 'referee'] as $step) {
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

    public function test_submitting_emails_the_applicant_the_registrar_and_the_referee(): void
    {
        Notification::fake();
        $registrar = $this->userWithRole(UserRole::Registrar);
        $admin = $this->userWithRole(UserRole::Admin);
        $application = $this->readyToSubmit([
            'referee_name' => 'Prof. A. Bello', 'referee_email' => 'bello@example.test', 'referee_relationship' => 'Head of department',
        ]);

        $this->actingAs($application->user)->post(route('membership.apply.submit'), ['declaration' => true])
            ->assertRedirect(route('membership.show'));

        Notification::assertSentTo($application->user, ApplicationReceived::class, fn ($n) => $n->kind === 'submitted');
        Notification::assertSentTo($registrar, ApplicationWaiting::class);
        Notification::assertNotSentTo($admin, ApplicationWaiting::class);
        Notification::assertSentOnDemand(RefereeRequest::class, fn ($n, $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'bello@example.test');
    }

    public function test_admins_hear_about_applications_while_there_is_no_registrar(): void
    {
        Notification::fake();
        $admin = $this->userWithRole(UserRole::Admin);
        $application = $this->readyToSubmit();

        $this->actingAs($application->user)->post(route('membership.apply.submit'), ['declaration' => true]);

        Notification::assertSentTo($admin, ApplicationWaiting::class);
        Notification::assertNothingSentTo(new AnonymousNotifiable);
    }

    public function test_each_decision_emails_the_applicant(): void
    {
        Notification::fake();
        $registrar = $this->userWithRole(UserRole::Registrar);

        $approve = $this->submitted();
        $this->actingAs($registrar)->post(route('admin.applications.decide', $approve), ['action' => 'approve']);
        Notification::assertSentTo($approve->user, ApplicationApproved::class);

        $recommend = $this->submitted();
        $this->actingAs($registrar)->post(route('admin.applications.decide', $recommend), [
            'action' => 'recommend', 'grade' => 'graduate', 'reason' => 'No publications yet.', 'improvements' => 'Publish one article.',
        ]);
        Notification::assertSentTo($recommend->user, GradeRecommended::class);

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

    public function test_accepting_appealing_and_a_refused_exemption_email_the_right_people(): void
    {
        Notification::fake();
        $registrar = $this->userWithRole(UserRole::Registrar);

        $accepting = $this->submitted();
        $this->actingAs($registrar)->post(route('admin.applications.decide', $accepting), [
            'action' => 'recommend', 'grade' => 'graduate', 'reason' => 'No publications yet.', 'improvements' => 'Publish one article.',
        ]);
        $this->actingAs($accepting->user->fresh())->post(route('membership.accept'));
        Notification::assertSentTo($accepting->user, ApplicationApproved::class);

        $appealing = $this->submitted();
        $this->actingAs($registrar)->post(route('admin.applications.decide', $appealing), [
            'action' => 'recommend', 'grade' => 'graduate', 'reason' => 'No publications yet.', 'improvements' => 'Publish one article.',
        ]);
        $this->actingAs($appealing->user->fresh())->post(route('membership.appeal'), [
            'appeal_reason' => 'Two of my articles were published last month; links are in my CV.',
        ]);
        Notification::assertSentTo($appealing->user, ApplicationReceived::class, fn ($n) => $n->kind === 'appealed');
        Notification::assertSentTo($registrar, ApplicationWaiting::class, fn ($n) => $n->kind === 'appealed');

        $exempt = $this->readyToSubmit(['route' => Route::Exemption, 'exam_status' => null]);
        $exempt->forceFill(['status' => ApplicationStatus::Submitted, 'exemption_status' => 'requested'])->save();
        $this->actingAs($registrar)->post(route('admin.applications.exemption', $exempt), ['decision' => 'refused', 'note' => 'Please take the exam.']);
        Notification::assertSentTo($exempt->user, ExemptionRefused::class, fn ($n) => $n->note === 'Please take the exam.');
    }

    public function test_a_failing_mail_server_does_not_undo_a_decision(): void
    {
        $registrar = $this->userWithRole(UserRole::Registrar);
        $application = $this->submitted();
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
        $this->get($page)->assertOk()->assertSee('Can you vouch for their character');

        $answer = URL::temporarySignedRoute('referee.answer', now()->addHour(), ['application' => $application->id]);
        $this->post($answer, ['answer' => 'confirmed'])->assertRedirect();

        $application->refresh();
        $this->assertSame('confirmed', $application->referee_status);
        $this->assertNotNull($application->referee_responded_at);
        $this->assertSame('referee_confirmed', $application->events()->reorder('id', 'desc')->value('action'));
        $this->get($page)->assertSee('Thank you, Prof. A. Bello.');
    }

    public function test_naming_a_different_referee_clears_the_previous_answer(): void
    {
        $application = $this->readyToSubmit([
            'referee_name' => 'Prof. A. Bello', 'referee_email' => 'bello@example.test', 'referee_relationship' => 'Head of department',
        ]);
        $application->forceFill(['referee_status' => 'declined', 'referee_responded_at' => now()])->save();

        $this->actingAs($application->user)->put(route('membership.apply.update', 'referee'), [
            'referee_name' => 'Dr. C. Eze', 'referee_email' => 'eze@example.test', 'referee_relationship' => 'PhD supervisor',
        ])->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertNull($application->referee_status);
        $this->assertNull($application->referee_responded_at);
    }

    public function test_the_emails_say_what_the_registrar_wrote(): void
    {
        $application = $this->submitted();
        $application->forceFill([
            'status' => ApplicationStatus::Recommended,
            'decided_grade' => Grade::Graduate,
            'decision_reason' => 'No publications are listed yet.',
            'decision_improvements' => 'Add evidence of one published article.',
        ])->save();

        $html = (string) (new GradeRecommended($application))->toMail($application->user)->render();

        $this->assertStringContainsString('The Registrar recommends that you join as Graduate Member', $html);
        $this->assertStringContainsString('No publications are listed yet.', $html);
        $this->assertStringContainsString('Add evidence of one published article.', $html);
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
