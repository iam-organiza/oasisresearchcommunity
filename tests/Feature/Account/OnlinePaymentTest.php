<?php

namespace Tests\Feature\Account;

use App\Account\Account;
use App\Account\Paystack;
use App\Enums\Account\ChargeKind;
use App\Enums\Account\PaymentMethod;
use App\Enums\Membership\Grade;
use App\Enums\UserRole;
use App\Models\Charge;
use App\Models\Membership;
use App\Models\OnlinePayment;
use App\Models\User;
use App\Notifications\Account\OnlinePaymentNeedsAttention;
use App\Notifications\Account\PaymentReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OnlinePaymentTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> What Paystack's verify answers differently from a clean success. */
    private array $verify = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paystack.secret_key' => 'sk_test_fake', 'services.paystack.public_key' => 'pk_test_fake']);
    }

    /** A Member owing their ₦150,000 induction fee. */
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
     * Paystack answers: initialise always works; verify says what we tell it.
     *
     * @param  array<string, mixed>  $verify
     */
    private function paystack(array $verify = []): void
    {
        $this->verify = $verify;
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true, 'message' => 'Authorization URL created',
                'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123', 'access_code' => 'abc123', 'reference' => 'x'],
            ]),
            'api.paystack.co/transaction/verify/*' => function (Request $request) {
                $reference = basename(parse_url($request->url(), PHP_URL_PATH));
                $attempt = OnlinePayment::query()->where('reference', $reference)->first();

                return Http::response([
                    'status' => true, 'message' => 'Verification successful',
                    'data' => [
                        'id' => 4099260516, 'status' => 'success', 'reference' => $reference,
                        'amount' => $attempt?->total_minor, 'currency' => $attempt?->currency,
                        'channel' => 'card', 'paid_at' => '2026-11-28T10:15:00.000Z', 'gateway_response' => 'Successful',
                        ...$this->verify,
                    ],
                ]);
            },
        ]);
    }

    public function test_paystacks_charge_is_added_at_its_published_rates(): void
    {
        // ₦150,000: 1.5% + ₦100 would be over ₦2,000, so the cap applies.
        $this->assertSame(15200000, Paystack::grossUp(15000000, 'NGN'));
        // ₦20,000 → ₦20,407: Paystack keeps ₦406.11 and OASIS gets at least ₦20,000.
        $this->assertSame(2040700, Paystack::grossUp(2000000, 'NGN'));
        // Under ₦2,500 there is no ₦100.
        $this->assertSame(101600, Paystack::grossUp(100000, 'NGN'));
        // Dollars: 3.9%. $105 → $109.27.
        $this->assertSame(10927, Paystack::grossUp(10500, 'USD'));

        foreach ([100000, 2000000, 5000000, 13000000, 15000000, 25000000] as $minor) {
            $total = Paystack::grossUp($minor, 'NGN');
            $fee = min(0.015 * $total + ($total >= 250000 ? 10000 : 0), 200000);
            $this->assertGreaterThanOrEqual($minor, $total - $fee, "OASIS must receive the full fee for {$minor}");
        }
    }

    public function test_a_member_pays_the_induction_fee_online(): void
    {
        Notification::fake();
        $this->paystack();
        $membership = $this->member();
        $user = $membership->user;
        $charge = $this->induction($membership);

        $this->actingAs($user)->get(route('membership.account'))
            ->assertInertia(fn ($page) => $page->where('online.minimum_part', 1000)->where('online.usd', false));

        // The induction fee is paid in one go, and the amount comes from the account, not the browser.
        $this->actingAs($user)->post(route('membership.payments.start', $charge), ['amount' => 50000, 'currency' => 'NGN'])
            ->assertSessionHasErrors('amount');

        $this->actingAs($user)->post(route('membership.payments.start', $charge), ['amount' => 150000, 'currency' => 'NGN'], ['X-Inertia' => 'true'])
            ->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://checkout.paystack.com/abc123');

        $attempt = OnlinePayment::query()->sole();
        $this->assertStringStartsWith('ORC-', $attempt->reference);
        $this->assertSame(15200000, $attempt->total_minor);
        $this->assertSame(200000, $attempt->fee_minor);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/transaction/initialize')
            && $request['amount'] === 15200000 && $request['currency'] === 'NGN' && $request['email'] === $user->email
            && $request['metadata']['site'] === 'OASIS Research Community'
            && $request->hasHeader('Authorization', 'Bearer sk_test_fake'));

        $this->actingAs($user)->get(route('membership.payments.return', ['reference' => $attempt->reference]))
            ->assertRedirect(route('membership.account'))
            ->assertSessionHas('payment_notice.tone', 'success');

        $attempt->refresh();
        $this->assertSame('paid', $attempt->status);
        $payment = $attempt->payment;
        $this->assertSame(150000, $payment->amount);
        $this->assertSame(PaymentMethod::Online, $payment->method);
        $this->assertSame($attempt->reference, $payment->reference);
        $this->assertTrue($membership->refresh()->isActive());
        Notification::assertSentTo($user, PaymentReceived::class);

        // Coming back again, or the scheduled check, records nothing twice.
        $this->actingAs($user)->get(route('membership.payments.return', ['reference' => $attempt->reference]));
        $this->artisan('payments:check-pending')->assertSuccessful();
        $this->assertSame(1, $charge->payments()->count());

        $this->actingAs($user)->get(route('receipts.show', $payment))
            ->assertSee('Paid online (Paystack)')->assertSee('₦152,000, including Paystack')->assertSee('charge of ₦2,000');
    }

    public function test_a_subscription_can_be_paid_online_in_parts(): void
    {
        Notification::fake();
        $this->paystack();
        $user = $this->member()->user;
        $subscription = Account::addCharge($user, ChargeKind::Subscription, 50000, null, 2027);

        $this->actingAs($user)->post(route('membership.payments.start', $subscription), ['amount' => 500, 'currency' => 'NGN'])
            ->assertSessionHasErrors(['amount' => 'Pay at least ₦1,000 at a time.']);
        $this->actingAs($user)->post(route('membership.payments.start', $subscription), ['amount' => 60000, 'currency' => 'NGN'])
            ->assertSessionHasErrors(['amount' => 'Only ₦50,000 is left to pay.']);
        $this->actingAs($user)->post(route('membership.payments.start', $subscription), ['amount' => 20000, 'currency' => 'NGN']);

        $attempt = OnlinePayment::query()->sole();
        $this->assertSame(2040700, $attempt->total_minor);
        $this->actingAs($user)->get(route('membership.payments.return', ['reference' => $attempt->reference]));

        $this->assertSame(30000, $subscription->refresh()->load('payments')->balance());
    }

    public function test_dollars_only_when_switched_on_and_the_amount_is_fixed_at_the_start(): void
    {
        Notification::fake();
        $this->paystack();
        $user = $this->member()->user;
        $charge = $this->induction($user->membership);
        DB::table('exchange_rates')->insert(['naira_per_dollar' => 1500, 'source' => 'open.er-api.com', 'fetched_at' => now()]);

        $this->actingAs($user)->post(route('membership.payments.start', $charge), ['amount' => 150000, 'currency' => 'USD'])
            ->assertSessionHasErrors(['currency' => 'Paying in dollars is not available yet.']);

        config(['services.paystack.usd_enabled' => true]);
        $this->actingAs($user)->post(route('membership.payments.start', $charge), ['amount' => 150000, 'currency' => 'USD']);
        $attempt = OnlinePayment::query()->sole();
        // ₦150,000 at ₦1,500 + 5% = $105, plus 3.9% = $109.27.
        $this->assertSame('USD', $attempt->currency);
        $this->assertSame(10927, $attempt->total_minor);
        $this->assertSame(1500.0, $attempt->naira_per_dollar);

        // The rate moves; the payment keeps the dollar amount it started with.
        DB::table('exchange_rates')->insert(['naira_per_dollar' => 1700, 'source' => 'open.er-api.com', 'fetched_at' => now()->addMinute()]);
        $this->actingAs($user)->get(route('membership.payments.return', ['reference' => $attempt->reference]));
        $this->assertSame('paid', $attempt->refresh()->status);
        $this->assertSame(150000, $attempt->payment->amount);
    }

    public function test_a_short_or_wrong_currency_payment_is_not_recorded_and_the_registrar_hears(): void
    {
        Notification::fake();
        $registrar = User::factory()->create();
        $registrar->forceFill(['role' => UserRole::Registrar])->save();
        $this->paystack(['amount' => 100000]);
        $membership = $this->member();
        $charge = $this->induction($membership);

        $this->actingAs($membership->user)->post(route('membership.payments.start', $charge), ['amount' => 150000, 'currency' => 'NGN']);
        $attempt = OnlinePayment::query()->sole();
        $this->actingAs($membership->user)->get(route('membership.payments.return', ['reference' => $attempt->reference]))
            ->assertSessionHas('payment_notice.tone', 'error');

        $this->assertSame('needs_attention', $attempt->refresh()->status);
        $this->assertSame(0, $charge->payments()->count());
        $this->assertFalse($membership->refresh()->isActive());
        Notification::assertSentTo($registrar, OnlinePaymentNeedsAttention::class);
    }

    public function test_two_payments_for_the_same_fee_do_not_both_count(): void
    {
        Notification::fake();
        $registrar = User::factory()->create();
        $registrar->forceFill(['role' => UserRole::Registrar])->save();
        $this->paystack();
        $membership = $this->member();
        $user = $membership->user;
        $charge = $this->induction($membership);

        // Two tabs: both start before either is confirmed.
        $this->actingAs($user)->post(route('membership.payments.start', $charge), ['amount' => 150000, 'currency' => 'NGN']);
        $this->actingAs($user)->post(route('membership.payments.start', $charge), ['amount' => 150000, 'currency' => 'NGN']);
        [$first, $second] = OnlinePayment::query()->orderBy('id')->get()->all();

        $this->actingAs($user)->get(route('membership.payments.return', ['reference' => $first->reference]));
        $this->actingAs($user)->get(route('membership.payments.return', ['reference' => $second->reference]));

        $this->assertSame(['paid', 'needs_attention'], [$first->refresh()->status, $second->refresh()->status]);
        $this->assertSame(1, $charge->payments()->count());
        Notification::assertSentTo($registrar, OnlinePaymentNeedsAttention::class);
    }

    public function test_failed_and_abandoned_attempts_and_other_peoples_charges(): void
    {
        $this->paystack(['status' => 'failed', 'gateway_response' => 'Declined']);
        $membership = $this->member();
        $charge = $this->induction($membership);

        $this->actingAs(User::factory()->create())->post(route('membership.payments.start', $charge), ['amount' => 150000, 'currency' => 'NGN'])
            ->assertNotFound();

        $this->actingAs($membership->user)->post(route('membership.payments.start', $charge), ['amount' => 150000, 'currency' => 'NGN']);
        $attempt = OnlinePayment::query()->sole();
        $this->actingAs($membership->user)->get(route('membership.payments.return', ['reference' => $attempt->reference]))
            ->assertSessionHas('payment_notice.tone', 'error');
        $this->assertSame('failed', $attempt->refresh()->status);

        // Started, never finished: after a day the scheduled check marks it abandoned.
        $this->verify = ['status' => 'abandoned', 'gateway_response' => 'The transaction was not completed'];
        $this->actingAs($membership->user)->post(route('membership.payments.start', $charge), ['amount' => 150000, 'currency' => 'NGN']);
        $open = OnlinePayment::query()->where('status', 'pending')->sole();
        $this->artisan('payments:check-pending')->assertSuccessful();
        $this->assertSame('pending', $open->refresh()->status, 'Too new to check.');

        $open->forceFill(['created_at' => now()->subHours(25), 'checked_at' => null])->save();
        $this->artisan('payments:check-pending')->assertSuccessful();
        $this->assertSame('abandoned', $open->refresh()->status);
    }

    public function test_without_keys_online_payment_is_off(): void
    {
        config(['services.paystack.secret_key' => null]);
        $membership = $this->member();

        $this->actingAs($membership->user)->get(route('membership.account'))->assertInertia(fn ($page) => $page->where('online', null));
        $this->actingAs($membership->user)->post(route('membership.payments.start', $this->induction($membership)), ['amount' => 150000, 'currency' => 'NGN'])
            ->assertSessionHasErrors(['amount' => 'Online payment is not switched on yet.']);
    }
}
