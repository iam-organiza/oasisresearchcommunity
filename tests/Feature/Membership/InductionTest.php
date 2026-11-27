<?php

namespace Tests\Feature\Membership;

use App\Account\Account;
use App\Account\Fees;
use App\Enums\Account\PaymentMethod;
use App\Enums\Membership\Grade;
use App\Enums\UserRole;
use App\Models\Membership;
use App\Models\State;
use App\Models\User;
use App\Notifications\Membership\MembershipActive;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InductionTest extends TestCase
{
    use RefreshDatabase;

    private function admitted(bool $paid = false): Membership
    {
        $membership = Membership::query()->create([
            'user_id' => User::factory()->create()->id, 'grade' => Grade::Member, 'grade_since' => today(), 'joined_on' => today(),
        ]);
        $induction = Account::addInductionFee($membership, null);
        if ($paid) {
            Account::recordPayment($induction->load('payments'), $induction->amount, PaymentMethod::BankTransfer, CarbonImmutable::now(), null);
        }

        return $membership->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function postedPack(): array
    {
        return [
            'choice' => 'post', 'address' => '12 Jos Road', 'city' => 'Lafia',
            'state_id' => State::query()->where('slug', 'nasarawa')->value('id'), 'phone' => '0803 000 0000',
        ];
    }

    public function test_a_number_is_issued_when_the_induction_fee_is_settled(): void
    {
        Notification::fake();
        $membership = $this->admitted();
        $this->assertNull($membership->number);
        $this->actingAs($membership->user)->get(route('membership.show'))->assertInertia(fn ($page) => $page->where('membership.number', null));

        $paid = $this->admitted(paid: true);
        $this->assertSame('OASIS/MEM/'.now()->year.'/0001', $paid->number);
        Notification::assertSentTo($paid->user, MembershipActive::class);
        Notification::assertNotSentTo($membership->user, MembershipActive::class);
    }

    public function test_choosing_the_ceremony_or_a_posted_pack(): void
    {
        $membership = $this->admitted();
        $user = $membership->user;

        $this->actingAs($user)->put(route('membership.induction'), ['choice' => 'post'])
            ->assertSessionHasErrors(['address', 'city', 'state_id', 'phone']);
        $this->actingAs($user)->put(route('membership.induction'), $this->postedPack())->assertSessionHasNoErrors();

        $membership->refresh();
        $this->assertSame('post', $membership->induction_delivery);
        $this->assertSame('12 Jos Road', $membership->delivery_address);
        // No postage while OASIS has not set one.
        $this->assertSame(0, $user->charges()->where('kind', 'postage')->count());

        // Changing to the ceremony forgets the address.
        $this->actingAs($user)->put(route('membership.induction'), ['choice' => 'ceremony', 'address' => 'ignored'])->assertSessionHasNoErrors();
        $membership->refresh();
        $this->assertSame('ceremony', $membership->induction_delivery);
        $this->assertNull($membership->delivery_address);
        $this->assertNull($membership->delivery_phone);
    }

    public function test_postage_goes_on_the_account_and_is_cancelled_if_they_change_their_mind(): void
    {
        app(Fees::class)->update(['postage' => 5000], 'Courier quote', null);
        $membership = $this->admitted();
        $user = $membership->user;

        $this->actingAs($user)->put(route('membership.induction'), $this->postedPack());
        $this->actingAs($user->fresh())->put(route('membership.induction'), $this->postedPack());
        $postage = $user->charges()->where('kind', 'postage')->sole();
        $this->assertSame(5000, $postage->amount);
        $this->assertSame('Induction pack postage', $postage->label());

        $this->actingAs($user->fresh())->put(route('membership.induction'), ['choice' => 'ceremony']);
        $this->assertNotNull($postage->refresh()->cancelled_at);
    }

    public function test_the_packs_page_says_why_a_pack_is_waiting(): void
    {
        $registrar = User::factory()->create();
        $registrar->forceFill(['role' => UserRole::Registrar])->save();
        $unpaid = $this->admitted();
        $this->actingAs($unpaid->user)->put(route('membership.induction'), $this->postedPack());
        $lapsed = $this->admitted(paid: true);
        $this->actingAs($lapsed->user)->put(route('membership.induction'), $this->postedPack());
        $lapsed->refresh()->forceFill(['lapsed_at' => now()])->save();
        $ready = $this->admitted(paid: true);
        $this->actingAs($ready->user)->put(route('membership.induction'), $this->postedPack());

        $rows = fn ($page) => collect($page->toArray()['props']['members']['data'])->keyBy('id');
        $this->actingAs($registrar)->get(route('admin.packs.index'))
            ->assertInertia(function ($page) use ($rows, $unpaid, $lapsed, $ready) {
                $data = $rows($page);
                $this->assertSame('fee', $data[$unpaid->id]['waiting']);
                $this->assertSame('lapsed', $data[$lapsed->id]['waiting']);
                $this->assertNull($data[$ready->id]['waiting']);
                $this->assertSame(['12 Jos Road', 'Lafia, Nasarawa'], $data[$ready->id]['lines']);
            });

        $this->actingAs($registrar)->patch(route('admin.packs.update', $ready), ['status' => 'posted']);
        $this->actingAs($registrar)->get(route('admin.packs.index', ['tab' => 'posted']))
            ->assertInertia(fn ($page) => $page->where('members.data.0.days_on_the_way', 0));
    }

    public function test_the_secretariat_posts_then_delivers_and_the_address_goes(): void
    {
        $registrar = User::factory()->create();
        $registrar->forceFill(['role' => UserRole::Registrar])->save();
        $waiting = $this->admitted();
        $this->actingAs($waiting->user)->put(route('membership.induction'), $this->postedPack());
        $active = $this->admitted(paid: true);
        $this->actingAs($active->user)->put(route('membership.induction'), $this->postedPack());

        $this->actingAs($registrar)->get(route('admin.packs.index'))
            ->assertInertia(fn ($page) => $page->component('admin/packs/index')->where('counts.to_post', 2)->has('members.data', 2));

        // Not before the induction fee is settled.
        $this->actingAs($registrar)->patch(route('admin.packs.update', $waiting), ['status' => 'posted'])->assertSessionHasErrors('status');

        $this->actingAs($registrar)->patch(route('admin.packs.update', $active), ['status' => 'posted'])->assertSessionHasNoErrors();
        $this->assertNotNull($active->refresh()->pack_posted_at);
        // Once posted, the member cannot change it.
        $this->actingAs($active->user->fresh())->put(route('membership.induction'), ['choice' => 'ceremony'])->assertSessionHasErrors('choice');

        $this->actingAs($registrar)->patch(route('admin.packs.update', $active), ['status' => 'delivered'])->assertSessionHasNoErrors();
        $active->refresh();
        $this->assertNotNull($active->pack_delivered_at);
        $this->assertNull($active->delivery_address);
        $this->assertNull($active->delivery_state_id);
        $this->assertNull($active->delivery_phone);

        $editor = User::factory()->create();
        $editor->forceFill(['role' => UserRole::Editor])->save();
        $this->actingAs($editor)->get(route('admin.packs.index'))->assertForbidden();
    }
}
