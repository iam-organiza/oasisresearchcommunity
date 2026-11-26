<?php

namespace Tests\Feature\Account;

use App\Account\Account;
use App\Account\Upgrades;
use App\Enums\Account\PaymentMethod;
use App\Enums\Membership\Grade;
use App\Enums\UserRole;
use App\Models\Charge;
use App\Models\Membership;
use App\Models\Post;
use App\Models\User;
use App\Notifications\Account\MembershipLapsed;
use App\Notifications\Account\MonthlyUpdate;
use App\Notifications\Account\SubscriptionDue;
use App\Notifications\Account\SubscriptionFinalReminder;
use App\Notifications\Account\UpgradeOffered;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-12-01 10:00', 'Africa/Lagos'));
    }

    /** A Member who has just paid their ₦150,000 induction fee. */
    private function activeMember(Grade $grade = Grade::Member): Membership
    {
        $membership = Membership::query()->create([
            'user_id' => User::factory()->create()->id,
            'number' => Membership::nextNumber($grade, 2026),
            'grade' => $grade,
            'grade_since' => today()->toDateString(),
            'joined_on' => today()->toDateString(),
        ]);
        $induction = Account::addInductionFee($membership, null);
        Account::recordPayment($induction, $induction->amount, PaymentMethod::BankTransfer, CarbonImmutable::now(), null);

        return $membership->refresh();
    }

    private function subscriptions(Membership $membership): Collection
    {
        return Charge::query()->where('user_id', $membership->user_id)->where('kind', 'subscription')->orderBy('due_on')->get();
    }

    private function pay(Charge $charge, ?int $amount = null): void
    {
        $charge->load('payments');
        Account::recordPayment($charge, $amount ?? $charge->balance(), PaymentMethod::BankTransfer, CarbonImmutable::now(), null);
    }

    private function day(string $date): void
    {
        $this->travelTo(CarbonImmutable::parse("{$date} 07:00", 'Africa/Lagos'));
        $this->artisan('membership:subscriptions')->assertSuccessful();
    }

    public function test_the_first_subscription_is_due_a_year_after_the_induction_fee(): void
    {
        $membership = $this->activeMember();

        $this->assertTrue($membership->isActive());
        $first = $this->subscriptions($membership)->sole();
        $this->assertSame('2027-12-01', $first->due_on->toDateString());
        $this->assertSame(50000, $first->amount);
        $this->assertSame('Subscription due 1 Dec 2027', $first->label());
    }

    public function test_members_admitted_before_the_website_start_at_their_next_anniversary(): void
    {
        $membership = Membership::query()->create([
            'user_id' => User::factory()->create()->id, 'number' => 'OASIS/MEM/2024/0001', 'grade' => Grade::Member,
            'grade_since' => '2024-03-01', 'joined_on' => '2024-03-01',
        ]);
        $induction = Account::addInductionFee($membership, null, amount: 55000);
        Account::recordPayment($induction, 55000, PaymentMethod::BeforeWebsite, CarbonImmutable::parse('2024-03-01'), null);
        // The fee was paid long ago; recorded today, but the clock follows the day it was recorded, rolled forward.
        $this->assertSame('2027-12-01', $this->subscriptions($membership)->sole()->due_on->toDateString());
    }

    public function test_reminders_then_lapsing_then_paying_brings_the_member_back(): void
    {
        $membership = $this->activeMember();
        $user = $membership->user;
        $subscription = $this->subscriptions($membership)->sole();

        $this->day('2027-11-30');
        Notification::assertNotSentTo($user, SubscriptionDue::class);

        $this->day('2027-12-01');
        Notification::assertSentToTimes($user, SubscriptionDue::class, 1);
        $this->day('2027-12-02');
        Notification::assertSentToTimes($user, SubscriptionDue::class, 1);
        Notification::assertNotSentTo($user, SubscriptionFinalReminder::class);

        // Grace runs to 31 December; the last reminder goes 7 days before.
        $this->assertSame('2027-12-31', $subscription->refresh()->graceEndsOn()->toDateString());
        $this->day('2027-12-24');
        Notification::assertSentToTimes($user, SubscriptionFinalReminder::class, 1);

        $this->day('2027-12-31');
        $this->assertTrue($membership->refresh()->isActive(), 'Still within the 30 days.');

        $this->day('2028-01-01');
        $membership->refresh();
        $this->assertTrue($membership->isLapsed());
        $this->assertFalse($membership->isActive());
        $this->assertSame('Lapsed', $membership->statusLabel());
        Notification::assertSentToTimes($user, MembershipLapsed::class, 1);
        $this->day('2028-01-02');
        Notification::assertSentToTimes($user, MembershipLapsed::class, 1);
        // No further year is charged while they are behind.
        $this->assertCount(1, $this->subscriptions($membership));

        // Paying in parts: still lapsed until it is all paid.
        $this->pay($subscription->refresh(), 20000);
        $this->assertTrue($membership->refresh()->isLapsed());
        $this->pay($subscription->refresh());
        $membership->refresh();
        $this->assertTrue($membership->isActive());
        $this->assertSame('reinstated', $user->accountEvents()->value('action'));
    }

    public function test_paying_early_and_the_next_year(): void
    {
        $membership = $this->activeMember();
        $this->pay($this->subscriptions($membership)->sole());

        // Paid early: nothing else is added until that year begins.
        $this->day('2027-06-01');
        $this->assertCount(1, $this->subscriptions($membership));

        $this->day('2027-12-01');
        $subscriptions = $this->subscriptions($membership);
        $this->assertCount(2, $subscriptions);
        $this->assertSame('2028-12-01', $subscriptions->last()->due_on->toDateString());
        Notification::assertNotSentTo($membership->user, SubscriptionDue::class);
    }

    public function test_upgrade_prices_follow_the_years_on_the_grade(): void
    {
        $membership = $this->activeMember(Grade::Graduate);

        // Under a year: the difference (₦150,000 − ₦100,000) plus 10% of it.
        $this->assertSame(55000, Upgrades::quote($membership, Grade::Member)['amount']);
        // A promotion: the new grade's fee less 20%.
        $this->assertSame(120000, Upgrades::quote($membership, Grade::Member, promotion: true)['amount']);

        $membership->forceFill(['grade_since' => '2025-11-15'])->save();
        $this->assertSame(120000, Upgrades::quote($membership->refresh(), Grade::Member)['amount'], 'After one year: less 20%.');
        $membership->forceFill(['grade_since' => '2024-11-15'])->save();
        $this->assertSame(127500, Upgrades::quote($membership->refresh(), Grade::Member)['amount'], 'After two years: less 15%.');
        $membership->forceFill(['grade_since' => '2023-11-15'])->save();
        $this->assertSame(150000, Upgrades::quote($membership->refresh(), Grade::Member)['amount'], 'After three years: the full fee.');

        $this->assertSame([Grade::Fellow, Grade::Member], Upgrades::higherGrades($membership));
    }

    public function test_an_upgrade_is_offered_paid_and_restarts_the_subscription_year(): void
    {
        $registrar = User::factory()->create();
        $registrar->forceFill(['role' => UserRole::Registrar])->save();
        $membership = $this->activeMember(Grade::Graduate);
        $user = $membership->user;

        $this->actingAs($registrar)->post(route('admin.accounts.upgrade', $user), ['grade' => 'student'])->assertSessionHasErrors('grade');
        $this->actingAs($registrar)->post(route('admin.accounts.upgrade', $user), ['grade' => 'member'])->assertSessionHasNoErrors();
        $this->actingAs($registrar)->post(route('admin.accounts.upgrade', $user), ['grade' => 'fellow'])
            ->assertSessionHasErrors(['grade' => 'An upgrade is already waiting to be paid. Cancel it first to offer a different one.']);

        $upgrade = $user->charges()->where('kind', 'upgrade')->sole();
        $this->assertSame('Upgrade to Member', $upgrade->label());
        $this->assertSame(55000, $upgrade->amount);
        Notification::assertSentTo($user, UpgradeOffered::class);
        $this->assertSame('Under a year on the grade: the difference of ₦50,000 plus a 10% admin fee', $user->accountEvents()->where('charge_id', $upgrade->id)->value('note'));

        // Paid in one go, six months later.
        $this->travelTo(CarbonImmutable::parse('2027-06-01 10:00', 'Africa/Lagos'));
        $this->pay($upgrade->refresh());

        $membership->refresh();
        $this->assertSame(Grade::Member, $membership->grade);
        $this->assertSame('2027-06-01', $membership->grade_since->toDateString());
        $this->assertNotNull($upgrade->refresh()->applied_at);

        // No subscription in the year an induction fee is paid: the old one is cancelled, the next is due a year on.
        $subscriptions = Charge::query()->where('user_id', $user->id)->where('kind', 'subscription')->orderBy('id')->get();
        $this->assertNotNull($subscriptions->first()->cancelled_at);
        $this->assertSame('2028-06-01', $subscriptions->last()->due_on->toDateString());
        $this->assertSame(50000, $subscriptions->last()->amount, 'At the new grade.');
    }

    public function test_the_monthly_update_and_stopping_it(): void
    {
        $membership = $this->activeMember();
        $user = $membership->user;
        $stopped = $this->activeMember()->user;
        $stopped->forceFill(['newsletter_opted_out_at' => now()])->save();
        Post::factory()->create(['title' => 'Writing a literature review that holds up', 'status' => 'published', 'visibility' => 'public', 'published_at' => now()->subDays(3)]);

        $this->artisan('membership:newsletter')->assertSuccessful();

        Notification::assertNotSentTo($stopped, MonthlyUpdate::class);
        Notification::assertSentTo($user, MonthlyUpdate::class, function (MonthlyUpdate $update) use ($user) {
            $html = (string) $update->toMail($user)->render();

            return str_contains($html, 'Your membership')
                && str_contains($html, 'Your next subscription, ₦50,000, is due on 1 December 2027')
                && str_contains($html, 'Writing a literature review that holds up')
                && str_contains($html, 'Stop these monthly emails');
        });

        $this->get(route('newsletter.unsubscribe', $user))->assertForbidden();
        $page = URL::signedRoute('newsletter.unsubscribe', ['user' => $user->id]);
        $this->get($page)->assertOk()->assertSee('Stop the monthly update for');
        $this->assertNull($user->refresh()->newsletter_opted_out_at, 'Opening the link alone changes nothing.');

        $this->post(URL::signedRoute('newsletter.update', ['user' => $user->id]), ['stop' => '1'])->assertRedirect();
        $this->assertNotNull($user->refresh()->newsletter_opted_out_at);
        $this->get($page)->assertSee('You won');
    }
}
