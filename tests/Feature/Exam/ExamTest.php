<?php

namespace Tests\Feature\Exam;

use App\Account\Account;
use App\Enums\Account\PaymentMethod;
use App\Enums\Membership\ApplicationStatus;
use App\Enums\Membership\Grade;
use App\Enums\Membership\Route;
use App\Exam\Exam;
use App\Exam\Paper;
use App\Membership\ApplicationSteps;
use App\Membership\GradeOffer;
use App\Models\Charge;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\MembershipApplication;
use App\Models\PrepMaterial;
use App\Models\User;
use App\Notifications\Membership\ApplicationApproved;
use App\Notifications\Membership\ExamResult;
use App\Notifications\Membership\GradeOffered;
use App\Notifications\Membership\MembershipActive;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        // Six questions in each of the 14 areas: 54 basic, 30 advanced.
        foreach (array_keys(ApplicationSteps::AREAS) as $area) {
            for ($i = 1; $i <= 6; $i++) {
                ExamQuestion::query()->create([
                    'area' => $area, 'question' => "{$area} question {$i}?",
                    'options' => ['Right', 'Wrong one', 'Wrong two', 'Wrong three'], 'correct_option' => 0,
                ]);
            }
        }
    }

    /** An applicant who accepted an offer at a grade and paid the exam fee. */
    private function candidate(Grade $grade = Grade::Member, bool $paid = true): MembershipApplication
    {
        $application = User::factory()->create()->membershipApplications()->create([
            'grade' => $grade, 'highest_qualification' => 'masters', 'role_type' => 'lecturer', 'currently_studying' => 'no',
            'years_experience' => 6, 'claimed_areas' => ['methodology', 'literature_review', 'supervision'],
            'route' => Route::Exam, 'exam_status' => 'will_take',
        ]);
        $application->forceFill(['status' => ApplicationStatus::Accepted, 'offered_grade' => $grade, 'accepted_at' => now()])->save();
        GradeOffer::chargeExamFee($application);
        if ($paid) {
            $this->payExamFee($application);
        }

        return $application->refresh();
    }

    private function payExamFee(MembershipApplication $application): void
    {
        $fee = Charge::query()->where('user_id', $application->user_id)->where('kind', 'exam')->whereNull('cancelled_at')->latest('id')->firstOrFail();
        Account::recordPayment($fee->load('payments'), $fee->balance(), PaymentMethod::BankTransfer, CarbonImmutable::now(), null);
    }

    /**
     * Takes an attempt through the website, getting `$right` questions right.
     */
    private function sit(MembershipApplication $application, int $right): ExamAttempt
    {
        $user = $application->user;
        $this->actingAs($user)->post(route('membership.exam.start'))->assertSessionHasNoErrors();
        $attempt = ExamAttempt::query()->where('user_id', $user->id)->latest('id')->firstOrFail();

        foreach ($attempt->questions()->get() as $question) {
            $option = $question->position <= $right ? $question->correct_option : ($question->correct_option + 1) % count($question->options);
            $this->actingAs($user)->post(route('membership.exam.answer', $attempt), ['position' => $question->position, 'option' => $option])
                ->assertSessionHasNoErrors();
        }
        $this->actingAs($user)->post(route('membership.exam.finish', $attempt))->assertRedirect(route('membership.exam'));

        return $attempt->refresh();
    }

    public function test_a_paper_has_fifty_questions_mixed_by_grade_and_weighted_to_claimed_areas(): void
    {
        $member = Paper::set($this->candidate(Grade::Member), Grade::Member);
        $this->assertCount(50, $member);
        $advanced = collect($member)->filter(fn ($q) => ApplicationSteps::AREAS[$q['area']]['advanced']);
        $this->assertCount(10, $advanced);
        // Every question from the areas they claim is used before others.
        $this->assertSame(6, collect($member)->where('area', 'methodology')->count());
        $this->assertSame(6, collect($member)->where('area', 'supervision')->count());
        // The options are shuffled, and the right one is still known.
        foreach ($member as $question) {
            $this->assertSame('Right', $question['options'][$question['correct_option']]);
        }

        $this->assertCount(20, collect(Paper::set($this->candidate(Grade::Fellow), Grade::Fellow))->filter(fn ($q) => ApplicationSteps::AREAS[$q['area']]['advanced']));
        $this->assertCount(0, collect(Paper::set($this->candidate(Grade::Student), Grade::Student))->filter(fn ($q) => ApplicationSteps::AREAS[$q['area']]['advanced']));
    }

    public function test_the_exam_opens_once_the_fee_is_paid_and_a_pass_admits_them(): void
    {
        $application = $this->candidate(paid: false);
        $user = $application->user;

        $this->actingAs($user)->get(route('membership.exam'))
            ->assertInertia(fn ($page) => $page->component('membership/exam')->where('canStart', false)->where('materials', null)->where('fee.amount', 20000));
        $this->actingAs($user)->post(route('membership.exam.start'))->assertSessionHasErrors('exam');

        $this->payExamFee($application);
        $this->actingAs($user)->get(route('membership.exam'))->assertInertia(fn ($page) => $page->where('canStart', true)->where('materials', []));

        $attempt = $this->sit($application, right: 31);

        $this->assertTrue($attempt->passed);
        $this->assertSame(62.0, $attempt->percent);
        $this->assertSame(50, array_sum(array_column($attempt->area_scores, 'total')));
        $application->refresh();
        $this->assertSame('passed', $application->exam_status);
        $this->assertSame(ApplicationStatus::Approved, $application->status);
        Notification::assertSentTo($user, ExamResult::class, fn (ExamResult $n) => $n->kind === 'passed');

        // Admitted straight away, with the exam fee taken off the induction fee; no number until it is paid.
        $membership = $user->refresh()->membership;
        $this->assertSame(Grade::Member, $membership->grade);
        $this->assertNull($membership->number);
        $induction = $user->charges()->where('kind', 'induction')->sole();
        $this->assertSame(130000, $induction->amount);
        $this->assertSame('Less the ₦20,000 exam fee', $induction->note);
        Notification::assertSentTo($user, ApplicationApproved::class);

        Account::recordPayment($induction->load('payments'), 130000, PaymentMethod::BankTransfer, CarbonImmutable::now(), null);
        $membership->refresh();
        $this->assertTrue($membership->isActive());
        $this->assertSame('OASIS/MEM/'.now()->year.'/0001', $membership->number);
        Notification::assertSentTo($user, MembershipActive::class);
    }

    public function test_three_attempts_per_fee_then_pay_again_and_wait_a_day(): void
    {
        $application = $this->candidate(Grade::Member);
        $user = $application->user;

        $this->sit($application, right: 20);
        Notification::assertSentTo($user, ExamResult::class, fn (ExamResult $n) => $n->kind === 'try_again');
        $this->sit($application, right: 22);
        $third = $this->sit($application, right: 24);
        $this->assertSame(3, $third->number);
        // 48% for a Member earns no lower grade: pay again.
        Notification::assertSentTo($user, ExamResult::class, fn (ExamResult $n) => $n->kind === 'pay_again');

        $this->actingAs($user)->get(route('membership.exam'))->assertInertia(fn ($page) => $page->where('needsNewFee', true)->where('canStart', false)->has('attempts', 3));
        $this->actingAs($user)->post(route('membership.exam.start'))->assertSessionHasErrors('exam');

        $this->actingAs($user)->post(route('membership.exam.pay-again'))->assertRedirect(route('membership.account'));
        $this->assertSame(2, $user->charges()->where('kind', 'exam')->count());
        $this->payExamFee($application);

        // The first attempt on a new fee waits 24 hours from the last one.
        $this->actingAs($user)->get(route('membership.exam'))->assertInertia(fn ($page) => $page->where('canStart', false)->whereNot('waitUntil', null));
        $this->travel(25)->hours();
        $this->actingAs($user)->get(route('membership.exam'))->assertInertia(fn ($page) => $page->where('canStart', true)->where('attemptsOnFee', 0));
        $this->assertSame(1, $this->sit($application, right: 40)->number);
        // No question repeats while the bank has unseen ones.
    }

    public function test_the_best_of_three_can_earn_a_lower_grade(): void
    {
        $application = $this->candidate(Grade::Fellow);
        $user = $application->user;

        $this->sit($application, right: 20);
        $this->sit($application, right: 27);
        $this->sit($application, right: 22);

        $application->refresh();
        // Best 54%: below 60% for Fellow, but 50-59% earns Member.
        $this->assertSame(ApplicationStatus::Offered, $application->status);
        $this->assertSame(Grade::Member, $application->offered_grade);
        $this->assertSame('band', $application->exam_status);
        $this->assertSame(54.0, (float) $application->exam_best_percent);
        Notification::assertSentTo($user, GradeOffered::class);

        // Accepting it admits them at once, at the lower grade's full induction fee (no exam fee off).
        $this->actingAs($user->fresh())->post(route('membership.accept'))->assertRedirect(route('membership.show'));
        $this->assertSame(ApplicationStatus::Approved, $application->refresh()->status);
        $this->assertSame(Grade::Member, $user->refresh()->membership->grade);
        $this->assertSame(150000, $user->charges()->where('kind', 'induction')->sole()->amount);
        $this->assertSame(1, $user->charges()->where('kind', 'exam')->count(), 'No second exam fee.');
    }

    public function test_offered_a_lower_grade_they_can_pay_again_and_keep_trying(): void
    {
        $application = $this->candidate(Grade::Fellow);
        foreach ([20, 27, 22] as $right) {
            $this->sit($application, right: $right);
        }

        $this->actingAs($application->user->fresh())->post(route('membership.exam.pay-again'))->assertRedirect(route('membership.account'));

        $application->refresh();
        $this->assertSame(ApplicationStatus::Accepted, $application->status);
        $this->assertSame(Grade::Fellow, $application->offered_grade);
        $this->assertTrue(GradeOffer::needsExam($application));
        $this->assertSame(2, $application->user->charges()->where('kind', 'exam')->count());
    }

    public function test_the_clock_is_the_servers(): void
    {
        $this->freezeTime();
        $application = $this->candidate();
        $user = $application->user;
        $this->actingAs($user)->post(route('membership.exam.start'));
        $attempt = ExamAttempt::query()->where('user_id', $user->id)->sole();
        $first = $attempt->questions()->first();
        $this->actingAs($user)->post(route('membership.exam.answer', $attempt), ['position' => $first->position, 'option' => $first->correct_option]);

        $this->actingAs($user)->get(route('membership.exam.sit', $attempt))
            ->assertInertia(fn ($page) => $page->component('membership/exam-sit')->where('attempt.seconds_left', 3600)->has('questions', 50)
                ->missing('questions.0.correct_option'));

        // Leaving the page does not stop the clock: after 60 minutes the attempt is marked as it stands.
        $this->travel(61)->minutes();
        $this->actingAs($user)->post(route('membership.exam.answer', $attempt), ['position' => 2, 'option' => 0])->assertSessionHasErrors('exam');
        $attempt->refresh();
        $this->assertTrue($attempt->isFinished());
        $this->assertSame(1, $attempt->correct);
        $this->assertSame(2.0, $attempt->percent);

        // Someone else's attempt is not theirs to see.
        $this->actingAs(User::factory()->create())->get(route('membership.exam.sit', $attempt))->assertNotFound();
    }

    public function test_attempts_left_running_are_marked_by_the_scheduled_job(): void
    {
        $application = $this->candidate();
        $this->actingAs($application->user)->post(route('membership.exam.start'));

        $this->travel(2)->hours();
        $this->artisan('exam:close-expired')->assertSuccessful();

        $this->assertTrue(ExamAttempt::query()->sole()->isFinished());
        Notification::assertSentTo($application->user, ExamResult::class, fn (ExamResult $n) => $n->kind === 'try_again');
    }

    public function test_prep_materials_open_with_the_paid_fee_and_the_exam_waits_for_the_bank(): void
    {
        Storage::fake('local');
        $material = PrepMaterial::query()->create([
            'title' => 'Methodology primer', 'area' => 'methodology', 'path' => UploadedFile::fake()->create('primer.pdf', 100, 'application/pdf')->store('prep-materials', 'local'),
            'original_name' => 'primer.pdf', 'mime_type' => 'application/pdf', 'size' => 100,
        ]);

        $unpaid = $this->candidate(paid: false);
        $this->actingAs($unpaid->user)->get(route('membership.exam.material', $material))->assertNotFound();

        $paid = $this->candidate();
        $this->actingAs($paid->user)->get(route('membership.exam.material', $material))->assertOk()->assertDownload('primer.pdf');
        $material->update(['published' => false]);
        $this->actingAs($paid->user)->get(route('membership.exam.material', $material))->assertNotFound();

        // With too few questions in use, the exam waits.
        ExamQuestion::query()->where('area', 'methodology')->update(['retired_at' => now()]);
        ExamQuestion::query()->whereIn('area', ['literature_review', 'data_collection'])->update(['retired_at' => now()]);
        $this->assertFalse(Paper::canSet(Grade::Member));
        $this->actingAs($paid->user)->get(route('membership.exam'))->assertInertia(fn ($page) => $page->where('bankReady', false)->where('canStart', false));
    }

    public function test_bands(): void
    {
        $this->assertSame(Grade::Member, Exam::band(Grade::Fellow, 59.9));
        $this->assertSame(Grade::Graduate, Exam::band(Grade::Fellow, 40));
        $this->assertNull(Exam::band(Grade::Fellow, 39.9));
        $this->assertSame(Grade::Graduate, Exam::band(Grade::Member, 50));
        $this->assertNull(Exam::band(Grade::Member, 49));
        $this->assertNull(Exam::band(Grade::Graduate, 59));
        $this->assertNull(Exam::band(Grade::Student, 59));
    }
}
