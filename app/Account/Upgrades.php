<?php

namespace App\Account;

use App\Enums\Account\ChargeKind;
use App\Enums\Membership\Grade;
use App\Membership\MembershipMail;
use App\Models\Charge;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\Account\UpgradeOffered;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moving a member up a grade (OASIS, 2026-11-27). The price depends on how
 * long they have held their grade:
 * - under a year: the difference in induction fees, plus the admin fee
 *   (10%) on that difference;
 * - one to two years: the new grade's induction fee less 20%;
 * - two to three years: less 15%;
 * - three years or more: the full induction fee.
 * A promotion, offered for three years of consistent training, is the new
 * grade's fee less 20%. The percentages are on the Fees screen.
 *
 * The Registrar offers an upgrade; it becomes an "Upgrade to …" charge, paid
 * in one go. Once it is settled the grade changes, and, as in any year an
 * induction fee is paid, there is no yearly subscription that year: the
 * subscription clock restarts.
 */
final class Upgrades
{
    /**
     * @return array{amount: int, rule: string}
     */
    public static function quote(Membership $membership, Grade $target, bool $promotion = false): array
    {
        $fees = app(Fees::class);
        $newFee = $target->inductionFee();

        if ($promotion) {
            $off = $fees->promotionDiscount();

            return ['amount' => self::less($newFee, $off), 'rule' => "Promotion: {$target->label()} induction fee less {$off}%"];
        }

        $years = $membership->yearsOnGrade();

        return match (true) {
            $years < 1 => (function () use ($newFee, $membership, $fees) {
                $difference = max(0, $newFee - $membership->grade->inductionFee());
                $admin = (int) ceil($difference * $fees->upgradePercent() / 100);

                return ['amount' => $difference + $admin, 'rule' => 'Under a year on the grade: the difference of ₦'.number_format($difference)." plus a {$fees->upgradePercent()}% admin fee"];
            })(),
            $years < 2 => ['amount' => self::less($newFee, $fees->upgradeDiscountAfterOneYear()), 'rule' => "One year or more on the grade: the induction fee less {$fees->upgradeDiscountAfterOneYear()}%"],
            $years < 3 => ['amount' => self::less($newFee, $fees->upgradeDiscountAfterTwoYears()), 'rule' => "Two years or more on the grade: the induction fee less {$fees->upgradeDiscountAfterTwoYears()}%"],
            default => ['amount' => $newFee, 'rule' => 'Three years or more on the grade: the full induction fee'],
        };
    }

    /**
     * The grades above the member's, highest first.
     *
     * @return list<Grade>
     */
    public static function higherGrades(Membership $membership): array
    {
        $cases = Grade::cases();
        $current = array_search($membership->grade, $cases, true);

        return array_slice($cases, 0, (int) $current);
    }

    /**
     * Offers an upgrade: adds the charge and emails the member.
     */
    public static function offer(Membership $membership, Grade $target, bool $promotion, ?User $actor): Charge
    {
        if (! in_array($target, self::higherGrades($membership), true)) {
            throw ValidationException::withMessages(['grade' => 'Choose a grade above their current one.']);
        }
        if ($membership->activated_at === null) {
            throw ValidationException::withMessages(['grade' => 'They can move up once their induction fee is settled.']);
        }
        $open = Charge::query()->where('user_id', $membership->user_id)->where('kind', ChargeKind::Upgrade->value)
            ->whereNull('cancelled_at')->whereNull('applied_at')->exists();
        if ($open) {
            throw ValidationException::withMessages(['grade' => 'An upgrade is already waiting to be paid. Cancel it first to offer a different one.']);
        }

        $quote = self::quote($membership, $target, $promotion);

        $charge = DB::transaction(function () use ($membership, $target, $quote, $actor): Charge {
            $charge = Account::addCharge($membership->user, ChargeKind::Upgrade, $quote['amount'], $actor, membership: $membership);
            $charge->forceFill(['grade' => $target])->save();
            // Keep how the price was worked out with the charge's history.
            $charge->user->accountEvents()->where('charge_id', $charge->id)->where('action', 'charge_added')->update(['note' => $quote['rule']]);

            return $charge;
        });

        MembershipMail::send($membership->user, new UpgradeOffered($charge, $quote['rule']));

        return $charge;
    }

    /**
     * The upgrade is paid (or waived): the member moves up.
     */
    public static function apply(Membership $membership, Charge $charge): void
    {
        DB::transaction(function () use ($membership, $charge) {
            $charge->forceFill(['applied_at' => now()])->save();
            $membership->forceFill(['grade' => $charge->grade, 'grade_since' => today()->toDateString()])->save();
            Account::log($charge, null, 'upgrade_applied', null, "Now {$charge->grade?->label()}");
            Subscriptions::restartClock($membership);
        });
    }

    private static function less(int $amount, int $percent): int
    {
        return (int) round($amount * (100 - $percent) / 100);
    }
}
