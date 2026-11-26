<?php

namespace Tests\Feature\Account;

use App\Account\Account;
use App\Enums\Account\ChargeKind;
use App\Enums\Membership\Grade;
use App\Enums\UserRole;
use App\Models\Charge;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\Account\PaymentReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function registrar(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::Registrar])->save();

        return $user;
    }

    /** A Member on the register with their ₦150,000 induction fee unpaid. */
    private function member(): Membership
    {
        $membership = Membership::query()->create([
            'user_id' => User::factory()->create()->id,
            'number' => Membership::nextNumber(Grade::Member, 2026),
            'grade' => Grade::Member,
            'joined_on' => '2026-11-20',
        ]);
        Account::addInductionFee($membership, null);

        return $membership->refresh();
    }

    private function induction(Membership $membership): Charge
    {
        return $membership->user->charges()->where('kind', 'induction')->sole();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pay(User $registrar, Charge $charge, int $amount, array $overrides = []): TestResponse
    {
        return $this->actingAs($registrar)->post(route('admin.charges.payments.store', $charge), [
            'amount' => $amount, 'method' => 'bank_transfer', 'paid_on' => now()->toDateString(), 'reference' => 'FT2611', ...$overrides,
        ]);
    }

    public function test_the_induction_fee_is_paid_in_one_go_and_activates_the_member(): void
    {
        Notification::fake();
        $registrar = $this->registrar();
        $membership = $this->member();
        $charge = $this->induction($membership);
        $year = now()->year;

        $this->assertFalse($membership->isActive());
        $this->assertSame(150000, $charge->amount);

        // Exam and induction fees are one-off payments (OASIS, 2026-11-27).
        $this->pay($registrar, $charge, 100000)->assertSessionHasErrors(['amount' => 'The induction fee is paid in one go. Record the full ₦150,000.']);
        $this->pay($registrar, $charge, 160000)->assertSessionHasErrors(['amount' => 'Only ₦150,000 is left to pay on this charge.']);
        $this->assertSame(0, $charge->payments()->count());

        $this->pay($registrar, $charge, 150000)->assertSessionHasNoErrors();
        $charge->refresh()->load('payments');
        $this->assertSame('Paid', $charge->status());
        $this->assertTrue($membership->refresh()->isActive());
        $this->assertSame("OASIS/RCT/{$year}/00001", $charge->payments->sole()->receipt_number);
        Notification::assertSentTo($membership->user, PaymentReceived::class);
    }

    public function test_a_subscription_can_be_paid_in_parts(): void
    {
        Notification::fake();
        $registrar = $this->registrar();
        $membership = $this->member();
        $subscription = Account::addCharge($membership->user, ChargeKind::Subscription, 50000, $registrar, 2027);
        $year = now()->year;

        $this->pay($registrar, $subscription, 20000)->assertSessionHasNoErrors();
        $subscription->refresh()->load('payments');
        $this->assertSame('Part paid', $subscription->status());
        $this->assertSame(30000, $subscription->balance());

        $this->pay($registrar, $subscription, 40000)->assertSessionHasErrors(['amount' => 'Only ₦30,000 is left to pay on this charge.']);
        $this->pay($registrar, $subscription, 30000)->assertSessionHasNoErrors();
        $subscription->refresh()->load('payments');
        $this->assertSame('Paid', $subscription->status());
        $this->assertSame(["OASIS/RCT/{$year}/00001", "OASIS/RCT/{$year}/00002"], $subscription->payments->pluck('receipt_number')->all());
    }

    public function test_voiding_a_payment_makes_the_amount_owed_again(): void
    {
        Notification::fake();
        $registrar = $this->registrar();
        $membership = $this->member();
        $charge = $this->induction($membership);
        $this->pay($registrar, $charge, 150000);
        $payment = $charge->payments()->sole();
        $this->assertTrue($membership->refresh()->isActive());

        $this->actingAs($registrar)->post(route('admin.payments.void', $payment), [])->assertSessionHasErrors('reason');
        $this->actingAs($registrar)->post(route('admin.payments.void', $payment), ['reason' => 'Recorded against the wrong member.'])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($payment->refresh()->voided_at);
        $this->assertSame(150000, $charge->refresh()->load('payments')->balance());
        $this->assertFalse($membership->refresh()->isActive());
        // The voided receipt no longer opens.
        $this->actingAs($membership->user)->get(route('receipts.show', $payment))->assertNotFound();
    }

    public function test_waivers_in_full_in_part_and_by_percentage(): void
    {
        Notification::fake();
        $registrar = $this->registrar();
        $membership = $this->member();
        $charge = $this->induction($membership);
        $waive = fn (array $data) => $this->actingAs($registrar)->post(route('admin.charges.waiver', $charge), $data);

        $waive(['type' => 'percent', 'value' => 40])->assertSessionHasErrors('reason');
        $waive(['type' => 'percent', 'value' => 140, 'reason' => 'Scholarship'])->assertSessionHasErrors('value');

        $waive(['type' => 'percent', 'value' => 40, 'reason' => 'Graduate scholarship'])->assertSessionHasNoErrors();
        $charge->refresh()->load('payments');
        $this->assertSame(60000, $charge->waived_amount);
        $this->assertSame(90000, $charge->balance());
        $this->assertSame('40% waived (₦60,000)', $charge->waiverDescription());

        // A waiver cannot take off what is already paid.
        $this->pay($registrar, $charge, 90000);
        $this->assertTrue($membership->refresh()->isActive());
        $waive(['type' => 'full', 'reason' => 'Council decision'])
            ->assertSessionHasErrors(['value' => '₦90,000 is already paid, so the most you can waive is ₦60,000.']);

        // Removing the waiver brings the amount back, and with it the member is inactive again.
        $this->actingAs($registrar)->delete(route('admin.charges.waiver.destroy', $charge))->assertSessionHasNoErrors();
        $this->assertSame(60000, $charge->refresh()->load('payments')->balance());
        $this->assertFalse($membership->refresh()->isActive());

        $waive(['type' => 'amount', 'value' => 60000, 'reason' => 'Council decision'])->assertSessionHasNoErrors();
        $this->assertTrue($membership->refresh()->isActive());
        // Added, waived, paid (which adds the first subscription), waiver removed, waived again;
        // refused attempts leave no trace.
        $this->assertSame(6, $membership->user->accountEvents()->count());
    }

    public function test_adding_and_cancelling_charges(): void
    {
        Notification::fake();
        $registrar = $this->registrar();
        $membership = $this->member();
        $user = $membership->user;
        $add = fn (array $data) => $this->actingAs($registrar)->post(route('admin.accounts.charges.store', $user), $data);

        $add(['kind' => 'subscription', 'amount' => 50000])->assertSessionHasErrors('year');
        $add(['kind' => 'subscription', 'amount' => 50000, 'year' => 2026])->assertSessionHasNoErrors();
        $add(['kind' => 'subscription', 'amount' => 50000, 'year' => 2026])->assertSessionHasErrors(['kind' => 'There is already a subscription charge for 2026.']);
        $add(['kind' => 'induction', 'amount' => 150000])->assertSessionHasErrors('kind');
        $add(['kind' => 'exam', 'amount' => 20000])->assertSessionHasNoErrors();

        $subscription = $user->charges()->where('kind', 'subscription')->sole();
        $this->assertSame('Subscription 2026', $subscription->label());

        // Charges with payments are not cancelled; void the payments first.
        $this->pay($registrar, $subscription, 10000);
        $this->actingAs($registrar)->post(route('admin.charges.cancel', $subscription), ['reason' => 'Added by mistake'])
            ->assertSessionHasErrors('reason');

        $exam = $user->charges()->where('kind', 'exam')->sole();
        $this->actingAs($registrar)->post(route('admin.charges.cancel', $exam), ['reason' => 'Exemption approved'])->assertSessionHasNoErrors();
        $exam->refresh()->load('payments');
        $this->assertSame('Cancelled', $exam->status());
        $this->assertSame(0, $exam->balance());
        $this->pay($registrar, $exam, 20000)->assertSessionHasErrors('reason');

        // Induction 150,000 + subscription 40,000 left; the cancelled exam fee is not owed.
        $this->actingAs($user)->get(route('membership.account'))
            ->assertInertia(fn ($page) => $page
                ->component('membership/account')
                ->where('statement.totals.owed', 190000)
                ->where('statement.totals.paid', 10000)
                ->has('statement.charges', 3)
                ->where('statement.history', []));
    }

    public function test_an_applicant_without_a_membership_can_owe_an_exam_fee(): void
    {
        $registrar = $this->registrar();
        $applicant = User::factory()->create();

        $this->actingAs($registrar)->post(route('admin.accounts.charges.store', $applicant), ['kind' => 'induction', 'amount' => 150000])
            ->assertSessionHasErrors(['amount' => 'An induction fee is added when someone joins the register.']);
        $this->actingAs($registrar)->post(route('admin.accounts.charges.store', $applicant), ['kind' => 'exam', 'amount' => 20000])
            ->assertSessionHasNoErrors();

        $this->assertSame(ChargeKind::Exam, $applicant->charges()->sole()->kind);
        $this->actingAs($registrar)->get(route('admin.accounts.show', $applicant))
            ->assertInertia(fn ($page) => $page->where('statement.totals.owed', 20000)->where('defaults.exam', 20000));
    }

    public function test_only_membership_staff_manage_accounts_and_receipts_are_private(): void
    {
        Notification::fake();
        $registrar = $this->registrar();
        $membership = $this->member();
        $charge = $this->induction($membership);
        $this->pay($registrar, $charge, 150000);
        $payment = $charge->payments()->sole();

        $editor = User::factory()->create();
        $editor->forceFill(['role' => UserRole::Editor])->save();
        $this->actingAs($editor)->get(route('admin.accounts.show', $membership->user))->assertForbidden();
        $this->actingAs($editor)->post(route('admin.charges.waiver', $charge), ['type' => 'full', 'reason' => 'x'])->assertForbidden();
        $this->actingAs($membership->user)->post(route('admin.payments.void', $payment), ['reason' => 'x'])->assertForbidden();

        $this->actingAs($membership->user)->get(route('receipts.show', $payment))->assertOk()
            ->assertSee($payment->receipt_number)->assertSee('₦150,000')->assertSee('Nothing; this charge is settled');
        $this->actingAs($registrar)->get(route('receipts.show', $payment))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('receipts.show', $payment))->assertNotFound();
    }

    public function test_the_receipt_email_says_what_is_left(): void
    {
        Notification::fake();
        $registrar = $this->registrar();
        $membership = $this->member();
        $subscription = Account::addCharge($membership->user, ChargeKind::Subscription, 50000, $registrar, 2027);
        $this->pay($registrar, $subscription, 20000);

        Notification::assertSentTo($membership->user, PaymentReceived::class, function (PaymentReceived $notification) use ($membership) {
            $html = (string) $notification->toMail($membership->user)->render();

            return str_contains($html, '₦20,000')
                && str_contains($html, 'Subscription 2027')
                && str_contains($html, '₦30,000 is still to pay on this charge.')
                && str_contains($html, route('membership.account'));
        });
    }
}
