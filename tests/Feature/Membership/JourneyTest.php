<?php

namespace Tests\Feature\Membership;

use App\Account\Account;
use App\Enums\Account\PaymentMethod;
use App\Enums\Membership\Grade;
use App\Models\Membership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_someone_who_has_not_applied_starts_at_apply(): void
    {
        $this->actingAs(User::factory()->create())->get(route('membership.show'))
            ->assertInertia(fn ($page) => $page
                ->has('journey', 6)
                ->where('journey.0.key', 'apply')
                ->where('journey.0.state', 'current')
                ->where('journey.5.state', 'upcoming'));
    }

    public function test_a_member_who_joined_without_an_online_application_has_no_timeline(): void
    {
        $membership = Membership::query()->create([
            'user_id' => User::factory()->create()->id, 'grade' => Grade::Member, 'grade_since' => today(), 'joined_on' => today(),
        ]);

        $this->actingAs($membership->user)->get(route('membership.show'))
            ->assertInertia(fn ($page) => $page->where('journey', []));
    }

    public function test_only_what_is_due_now_counts_as_owed(): void
    {
        $membership = Membership::query()->create([
            'user_id' => User::factory()->create()->id, 'grade' => Grade::Member, 'grade_since' => today(), 'joined_on' => today(),
        ]);
        $induction = Account::addInductionFee($membership, null);

        $this->actingAs($membership->user)->get(route('membership.show'))
            ->assertInertia(fn ($page) => $page
                ->where('membership.owed', $induction->amount)
                ->where('membership.next_due', null));

        // Paying the induction fee activates the membership; next year's subscription is upcoming, not owed.
        Account::recordPayment($induction->load('payments'), $induction->amount, PaymentMethod::BankTransfer, CarbonImmutable::now(), null);
        $subscription = $membership->user->charges()->where('kind', 'subscription')->first();

        $this->actingAs($membership->user->fresh())->get(route('membership.show'))
            ->assertInertia(fn ($page) => $page
                ->where('membership.owed', 0)
                ->where('membership.next_due', $subscription ? [
                    'amount' => $subscription->amount,
                    'on' => $subscription->due_on->format('j M Y'),
                ] : null));
    }

    public function test_the_payments_page_splits_due_now_from_coming_up(): void
    {
        $membership = Membership::query()->create([
            'user_id' => User::factory()->create()->id, 'grade' => Grade::Member, 'grade_since' => today(), 'joined_on' => today(),
        ]);
        $induction = Account::addInductionFee($membership, null);
        Account::recordPayment($induction->load('payments'), $induction->amount, PaymentMethod::BankTransfer, CarbonImmutable::now(), null);
        $subscription = $membership->user->charges()->where('kind', 'subscription')->first();

        $this->actingAs($membership->user->fresh())->get(route('membership.account'))
            ->assertInertia(fn ($page) => $page
                ->where('statement.totals.due_now', 0)
                ->where('statement.totals.owed', $subscription?->amount ?? 0)
                ->where('statement.totals.coming_up', $subscription ? [
                    'amount' => $subscription->amount,
                    'on' => $subscription->due_on->format('j M Y'),
                    'label' => $subscription->label(),
                ] : null));
    }
}
