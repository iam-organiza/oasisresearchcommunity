<?php

namespace Tests\Feature\Account;

use App\Account\Account;
use App\Account\ExchangeRate;
use App\Account\Fees;
use App\Enums\Membership\Grade;
use App\Enums\UserRole;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\Account\ExchangeRateStale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FeesTest extends TestCase
{
    use RefreshDatabase;

    private function staff(UserRole $role = UserRole::Registrar): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    /**
     * Today's fees as the form sends them, with some changed.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function form(array $changes = []): array
    {
        $form = [
            'exam' => 20000, 'postage' => 0, 'upgrade_percent' => 10, 'upgrade_discount_1y' => 20, 'upgrade_discount_2y' => 15, 'promotion_discount' => 20,
            'usd_rate_override' => null, 'reason' => 'Council meeting, 1 December 2026',
        ];
        foreach (Grade::cases() as $grade) {
            $form['induction'][$grade->value] = $grade->inductionFee();
            $form['subscription'][$grade->value] = $grade->annualDues();
        }

        return array_replace_recursive($form, $changes);
    }

    private function rate(float $nairaPerDollar, ?string $when = null): void
    {
        DB::table('exchange_rates')->insert(['naira_per_dollar' => $nairaPerDollar, 'source' => 'open.er-api.com', 'fetched_at' => $when ?? now()]);
    }

    public function test_the_starting_fees_are_oasis_figures(): void
    {
        $fees = app(Fees::class);

        $this->assertSame(20000, $fees->exam());
        // OASIS, 27 November 2026: 10% admin fee within the first year; 20% and 15% off after one and two years.
        $this->assertSame(10, $fees->upgradePercent());
        $this->assertSame([20, 15, 20], [$fees->upgradeDiscountAfterOneYear(), $fees->upgradeDiscountAfterTwoYears(), $fees->promotionDiscount()]);
        $this->assertNull($fees->rateOverride());
        $this->assertSame([250000, 150000, 100000, 50000], array_map(fn (Grade $grade) => $grade->inductionFee(), Grade::cases()));
        $this->assertSame([60000, 50000, 40000, 20000], array_map(fn (Grade $grade) => $grade->annualDues(), Grade::cases()));
    }

    public function test_staff_change_fees_with_a_reason_and_each_change_is_logged(): void
    {
        $registrar = $this->staff();

        $this->actingAs($registrar)->put(route('admin.fees.update'), $this->form(['reason' => '']))->assertSessionHasErrors('reason');
        $this->actingAs($registrar)->put(route('admin.fees.update'), $this->form(['upgrade_percent' => 120]))->assertSessionHasErrors('upgrade_percent');

        $this->actingAs($registrar)->put(route('admin.fees.update'), $this->form([
            'induction' => ['member' => 175000],
            'exam' => 25000,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(175000, Grade::Member->inductionFee());
        $this->assertSame(25000, app(Fees::class)->exam());
        // Only what changed is logged.
        $changes = DB::table('price_setting_changes')->where('user_id', $registrar->id)->orderBy('id')->get();
        $this->assertSame(['exam', 'induction.member'], $changes->pluck('key')->all());
        $this->assertSame([150000, 175000], [(int) $changes[1]->old_amount, (int) $changes[1]->new_amount]);
        $this->assertSame('Council meeting, 1 December 2026', $changes[0]->reason);
        $this->assertSame($registrar->id, (int) $changes[0]->user_id);

        $this->actingAs($registrar)->get(route('admin.fees.index'))
            ->assertInertia(fn ($page) => $page
                ->component('admin/fees/index')
                ->where('exam', 25000)
                ->has('history', 3)
                ->where('history.0.label', 'Member induction fee'));
    }

    public function test_a_new_fee_applies_to_new_charges_only(): void
    {
        $member = Membership::query()->create([
            'user_id' => User::factory()->create()->id, 'number' => 'OASIS/MEM/2026/0001', 'grade' => Grade::Member, 'joined_on' => '2026-11-20',
        ]);
        $charge = Account::addInductionFee($member, null);

        app(Fees::class)->update(['induction.member' => 175000], 'Council', null);

        $this->assertSame(150000, $charge->refresh()->amount);
        $later = Membership::query()->create([
            'user_id' => User::factory()->create()->id, 'number' => 'OASIS/MEM/2026/0002', 'grade' => Grade::Member, 'joined_on' => '2026-12-02',
        ]);
        $this->assertSame(175000, Account::addInductionFee($later, null)->amount);
    }

    public function test_only_membership_staff_open_the_fees_screen(): void
    {
        $editor = $this->staff(UserRole::Editor);

        $this->actingAs($editor)->get(route('admin.fees.index'))->assertForbidden();
        $this->actingAs($editor)->put(route('admin.fees.update'), $this->form())->assertForbidden();
        $this->actingAs($this->staff(UserRole::Admin))->get(route('admin.fees.index'))->assertOk();
    }

    public function test_dollar_prices_add_the_margin_and_round_up(): void
    {
        $rates = app(ExchangeRate::class);
        $this->assertNull($rates->dollars(150000), 'No rate yet, no dollar price.');

        $this->rate(1500);
        // 150,000 / 1,500 = 100, plus 5% = 105.
        $this->assertSame(105, $rates->dollars(150000));
        // 20,000 / 1,500 = 13.33, plus 5% = 14.0, so exactly 14.
        $this->assertSame(14, $rates->dollars(20000));
        // 50,000 / 1,500 = 33.33, plus 5% = 35.0.
        $this->assertSame(35, $rates->dollars(50000));

        // A rate set by hand wins over the daily one.
        app(Fees::class)->update(['usd_rate_override' => 2000], 'Source down', null);
        $this->assertSame(2000.0, app(ExchangeRate::class)->current());
        $this->assertSame(79, app(ExchangeRate::class)->dollars(150000));
    }

    public function test_the_daily_fetch_stores_a_sane_rate_and_keeps_the_last_good_one(): void
    {
        Http::fake(['open.er-api.com/*' => Http::sequence()
            ->push(['result' => 'success', 'rates' => ['NGN' => 1532.4567]])
            ->push(['result' => 'success', 'rates' => ['NGN' => 3]])
            ->push('Server error', 500)]);

        $this->artisan('fees:refresh-rate')->assertSuccessful();
        $this->assertSame(1532.4567, app(ExchangeRate::class)->current());

        // A silly reading and an outage leave the last good rate in place.
        $this->artisan('fees:refresh-rate')->assertSuccessful();
        $this->artisan('fees:refresh-rate')->assertSuccessful();
        $this->assertSame(1, DB::table('exchange_rates')->count());
        $this->assertSame(1532.4567, app(ExchangeRate::class)->current());
    }

    public function test_admins_hear_once_a_week_when_the_rate_is_stale(): void
    {
        Notification::fake();
        Http::fake(['open.er-api.com/*' => Http::response('Server error', 500)]);
        $admin = $this->staff(UserRole::Admin);
        $this->rate(1500, now()->subDays(8)->toDateTimeString());

        $this->assertTrue(app(ExchangeRate::class)->isStale());
        $this->artisan('fees:refresh-rate')->assertSuccessful();
        $this->artisan('fees:refresh-rate')->assertSuccessful();

        Notification::assertSentToTimes($admin, ExchangeRateStale::class, 1);

        // A rate set by hand is never stale.
        app(Fees::class)->update(['usd_rate_override' => 1600], 'Source down', null);
        $this->assertFalse(app(ExchangeRate::class)->isStale());
    }

    public function test_applicants_and_members_see_dollar_prices(): void
    {
        $this->rate(1500);
        $applicant = User::factory()->create();
        $this->actingAs($applicant)->post(route('membership.apply.start'));

        $this->actingAs($applicant->fresh())->get(route('membership.apply.edit', 'grade'))
            ->assertInertia(fn ($page) => $page
                ->where('options.grades.1.induction_fee', 150000)
                ->where('options.grades.1.induction_usd', 105)
                ->where('options.grades.1.annual_usd', 35));
    }
}
