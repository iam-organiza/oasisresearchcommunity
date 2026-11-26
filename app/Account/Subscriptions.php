<?php

namespace App\Account;

use App\Enums\Account\ChargeKind;
use App\Membership\MembershipMail;
use App\Models\Charge;
use App\Models\Membership;
use App\Notifications\Account\MembershipLapsed;
use App\Notifications\Account\SubscriptionDue;
use App\Notifications\Account\SubscriptionFinalReminder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Yearly subscriptions (OASIS, 2026-11-27):
 * - none in a year an induction fee is paid: the first is due 12 months
 *   after the induction fee is paid, and an upgrade restarts the clock;
 * - each one can be paid early, and in parts;
 * - the next is added when the paid one's year begins (its due date);
 * - an unpaid one gets 30 days' grace with reminders (on the due date and
 *   7 days before the end), then the member lapses until they pay.
 *
 * There is at most one subscription waiting at a time: a lapsed member is
 * not charged for further years until they catch up.
 */
final class Subscriptions
{
    /** Days before the end of the grace period that the last reminder goes. */
    public const FINAL_REMINDER_DAYS = 7;

    /**
     * Adds the first subscription when a member becomes active, due 12
     * months after their induction fee was paid. Members admitted before
     * the website whose first anniversary has passed start at the next one.
     */
    public static function start(Membership $membership): ?Charge
    {
        if (self::latest($membership) !== null || $membership->activated_at === null) {
            return null;
        }

        $due = CarbonImmutable::parse($membership->activated_at)->startOfDay()->addYear();
        while ($due->lt(today())) {
            $due = $due->addYear();
        }

        return self::add($membership, $due);
    }

    /**
     * Once the year a paid subscription covers has begun, adds the next one.
     * Returns it, or null when nothing was due.
     */
    public static function addNextIfDue(Membership $membership): ?Charge
    {
        if (! $membership->isActive() && ! $membership->isLapsed()) {
            return null;
        }

        $latest = self::latest($membership);
        if ($latest === null) {
            return self::start($membership);
        }
        if ($latest->due_on === null || $latest->due_on->gt(today()) || ! $latest->isSettled()) {
            return null;
        }

        return self::add($membership, $latest->due_on->addYear());
    }

    /**
     * An upgrade's induction fee covers the year it is paid in: unpaid
     * subscriptions not yet due are cancelled and the next one is due 12
     * months from today. Overdue ones stay owed.
     */
    public static function restartClock(Membership $membership): Charge
    {
        Charge::query()
            ->with('payments')
            ->where('user_id', $membership->user_id)
            ->where('kind', ChargeKind::Subscription->value)
            ->whereNull('cancelled_at')
            ->where('due_on', '>', today())
            ->get()
            ->filter(fn (Charge $charge) => $charge->paidAmount() === 0)
            ->each(function (Charge $charge) {
                $charge->forceFill([
                    'cancelled_at' => now(),
                    'cancel_reason' => 'No yearly subscription in a year an induction fee is paid; the upgrade starts a new year.',
                ])->save();
                Account::log($charge, null, 'charge_cancelled', $charge->amount, $charge->cancel_reason);
            });

        return self::add($membership, today()->addYear());
    }

    /**
     * The unpaid subscription whose grace has run out, if any.
     */
    public static function overdue(Membership $membership): ?Charge
    {
        return self::open($membership)->first(fn (Charge $charge) => $charge->graceEndsOn()?->lt(today()) === true);
    }

    /**
     * The daily round for one member: add the next subscription if its year
     * has begun, send the due and last reminders, and lapse them once the
     * grace is over. Each email goes once.
     */
    public static function run(Membership $membership): void
    {
        self::addNextIfDue($membership);

        foreach (self::open($membership) as $charge) {
            $graceEnds = $charge->graceEndsOn();
            if ($graceEnds === null || $charge->due_on === null || $charge->due_on->gt(today())) {
                continue;
            }

            if ($graceEnds->lt(today())) {
                if ($membership->lapsed_at === null && $membership->activated_at !== null) {
                    $membership->forceFill(['lapsed_at' => now()])->save();
                    Account::log($charge, null, 'lapsed');
                    MembershipMail::send($membership->user, new MembershipLapsed($membership, $charge));
                }

                continue;
            }

            if ($charge->reminded_due_at === null) {
                $charge->forceFill(['reminded_due_at' => now()])->save();
                MembershipMail::send($membership->user, new SubscriptionDue($charge));
            } elseif ($charge->reminded_final_at === null && $graceEnds->subDays(self::FINAL_REMINDER_DAYS)->lte(today())) {
                $charge->forceFill(['reminded_final_at' => now()])->save();
                MembershipMail::send($membership->user, new SubscriptionFinalReminder($charge));
            }
        }
    }

    /**
     * The subscription a member should look at next: the oldest unpaid one,
     * or the upcoming one. For pages and the newsletter.
     */
    public static function next(Membership $membership): ?Charge
    {
        return self::open($membership)->first() ?? self::latest($membership);
    }

    private static function add(Membership $membership, CarbonImmutable $due): Charge
    {
        return DB::transaction(function () use ($membership, $due): Charge {
            $charge = Account::addCharge($membership->user, ChargeKind::Subscription, $membership->grade->annualDues(), null, $due->year, $membership);
            $charge->forceFill(['due_on' => $due->toDateString()])->save();

            return $charge;
        });
    }

    private static function latest(Membership $membership): ?Charge
    {
        return Charge::query()
            ->with('payments')
            ->where('user_id', $membership->user_id)
            ->where('kind', ChargeKind::Subscription->value)
            ->whereNull('cancelled_at')
            ->whereNotNull('due_on')
            ->orderByDesc('due_on')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Unpaid subscriptions, oldest due first.
     *
     * @return Collection<int, Charge>
     */
    private static function open(Membership $membership): Collection
    {
        return Charge::query()
            ->with('payments')
            ->where('user_id', $membership->user_id)
            ->where('kind', ChargeKind::Subscription->value)
            ->whereNull('cancelled_at')
            ->whereNotNull('due_on')
            ->orderBy('due_on')
            ->get()
            ->filter(fn (Charge $charge) => $charge->balance() > 0)
            ->values();
    }
}
