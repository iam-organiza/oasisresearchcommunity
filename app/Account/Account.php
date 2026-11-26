<?php

namespace App\Account;

use App\Enums\Account\ChargeKind;
use App\Enums\Account\PaymentMethod;
use App\Enums\Account\WaiverType;
use App\Membership\MembershipMail;
use App\Models\AccountEvent;
use App\Models\Charge;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Account\PaymentReceived;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every change to a person's account goes through here: adding a charge,
 * waiving it, recording or voiding a payment, cancelling a charge. Each one
 * is logged, and the member's "active" date follows their induction fee.
 */
final class Account
{
    public static function addCharge(User $user, ChargeKind $kind, int $amount, ?User $actor, ?int $year = null, ?Membership $membership = null): Charge
    {
        return DB::transaction(function () use ($user, $kind, $amount, $actor, $year, $membership): Charge {
            $membership ??= $user->membership;

            $charge = Charge::query()->create([
                'user_id' => $user->id,
                'membership_id' => $membership?->id,
                'kind' => $kind,
                'year' => $kind === ChargeKind::Subscription ? $year : null,
                'grade' => $kind === ChargeKind::Induction ? $membership?->grade : null,
                'amount' => $amount,
                'created_by' => $actor?->id,
            ]);
            self::log($charge, $actor, 'charge_added', $amount);
            self::refreshMembership($user);

            return $charge;
        });
    }

    /**
     * The induction fee, added when someone joins the register: their
     * grade's fee unless another amount is given (members admitted before the
     * website paid the fee of their day). A waiver given during the
     * application review comes with it.
     */
    public static function addInductionFee(Membership $membership, ?User $actor, bool $waived = false, string $waiverReason = 'Waived during the application review.', ?int $amount = null): Charge
    {
        return DB::transaction(function () use ($membership, $actor, $waived, $waiverReason, $amount): Charge {
            $charge = self::addCharge($membership->user, ChargeKind::Induction, $amount ?? $membership->grade->inductionFee(), $actor, membership: $membership);

            if ($waived) {
                self::waive($charge, WaiverType::Full, null, $waiverReason, $actor);
            }

            return $charge;
        });
    }

    /**
     * Sets (or replaces) the waiver on a charge. It may not take off more
     * than is still unpaid.
     */
    public static function waive(Charge $charge, WaiverType $type, ?int $value, string $reason, ?User $actor): Charge
    {
        return DB::transaction(function () use ($charge, $type, $value, $reason, $actor): Charge {
            $charge = Charge::query()->with('payments')->lockForUpdate()->findOrFail($charge->id);
            self::refuseIfCancelled($charge);

            $waived = $type->waives($charge->amount, $value);
            $unpaid = $charge->amount - $charge->paidAmount();
            if ($waived > $unpaid) {
                throw ValidationException::withMessages([
                    'value' => '₦'.number_format($charge->paidAmount()).' is already paid, so the most you can waive is ₦'.number_format($unpaid).'.',
                ]);
            }

            $charge->forceFill([
                'waiver_type' => $type,
                'waiver_value' => $type === WaiverType::Full ? null : $value,
                'waived_amount' => $waived,
                'waiver_reason' => $reason,
                'waived_by' => $actor?->id,
                'waived_at' => now(),
            ])->save();
            self::log($charge, $actor, 'waiver_set', $waived, $reason);
            self::refreshMembership($charge->user);

            return $charge;
        });
    }

    public static function removeWaiver(Charge $charge, ?User $actor): Charge
    {
        return DB::transaction(function () use ($charge, $actor): Charge {
            $charge = Charge::query()->lockForUpdate()->findOrFail($charge->id);
            self::refuseIfCancelled($charge);
            $removed = $charge->waived_amount;

            $charge->forceFill([
                'waiver_type' => null, 'waiver_value' => null, 'waived_amount' => 0,
                'waiver_reason' => null, 'waived_by' => null, 'waived_at' => null,
            ])->save();
            self::log($charge, $actor, 'waiver_removed', $removed);
            self::refreshMembership($charge->user);

            return $charge;
        });
    }

    /**
     * Records money received against a charge and emails the payer a
     * receipt. A payment may not be more than is still to pay.
     */
    public static function recordPayment(Charge $charge, int $amount, PaymentMethod $method, CarbonImmutable $paidOn, ?User $actor, ?string $reference = null, ?string $note = null): Payment
    {
        $payment = DB::transaction(function () use ($charge, $amount, $method, $paidOn, $actor, $reference, $note): Payment {
            $charge = Charge::query()->with('payments')->lockForUpdate()->findOrFail($charge->id);
            self::refuseIfCancelled($charge);

            if ($amount > $charge->balance()) {
                throw ValidationException::withMessages([
                    'amount' => $charge->balance() === 0
                        ? 'Nothing is left to pay on this charge.'
                        : 'Only ₦'.number_format($charge->balance()).' is left to pay on this charge.',
                ]);
            }

            $payment = Payment::query()->create([
                'charge_id' => $charge->id,
                'user_id' => $charge->user_id,
                'receipt_number' => Payment::nextReceiptNumber((int) now()->year),
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'paid_on' => $paidOn->toDateString(),
                'note' => $note,
                'recorded_by' => $actor?->id,
            ]);
            self::log($charge, $actor, 'payment_recorded', $amount, $reference, $payment);
            self::refreshMembership($charge->user);

            return $payment;
        });

        if ($method !== PaymentMethod::BeforeWebsite) {
            MembershipMail::send($payment->user, new PaymentReceived($payment->fresh(['charge.payments'])));
        }

        return $payment;
    }

    /**
     * Voids a payment recorded by mistake. It stays on the account, struck
     * through, with the reason.
     */
    public static function voidPayment(Payment $payment, string $reason, ?User $actor): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $actor): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->isVoided()) {
                throw ValidationException::withMessages(['reason' => 'This payment is already voided.']);
            }

            $payment->forceFill(['voided_at' => now(), 'void_reason' => $reason, 'voided_by' => $actor?->id])->save();

            /** @var Charge $charge */
            $charge = $payment->charge()->with('payments')->firstOrFail();
            self::log($charge, $actor, 'payment_voided', $payment->amount, $reason, $payment);
            self::refreshMembership($payment->user);

            return $payment;
        });
    }

    /**
     * Cancels a charge added by mistake. Only possible while nothing has
     * been paid against it; void those payments first.
     */
    public static function cancel(Charge $charge, string $reason, ?User $actor): Charge
    {
        return DB::transaction(function () use ($charge, $reason, $actor): Charge {
            $charge = Charge::query()->with('payments')->lockForUpdate()->findOrFail($charge->id);
            self::refuseIfCancelled($charge);

            if ($charge->paidAmount() > 0) {
                throw ValidationException::withMessages(['reason' => 'Payments are recorded against this charge. Void them first.']);
            }

            $charge->forceFill(['cancelled_at' => now(), 'cancel_reason' => $reason, 'cancelled_by' => $actor?->id])->save();
            self::log($charge, $actor, 'charge_cancelled', $charge->amount, $reason);
            self::refreshMembership($charge->user);

            return $charge;
        });
    }

    /**
     * A member is active from the moment their induction fee is settled,
     * and stops being active if it becomes unsettled again (a voided
     * payment, a removed waiver).
     */
    public static function refreshMembership(User $user): void
    {
        $membership = $user->membership()->first();
        if (! $membership) {
            return;
        }

        $settled = Charge::query()
            ->with('payments')
            ->where('user_id', $user->id)
            ->where('kind', ChargeKind::Induction->value)
            ->whereNull('cancelled_at')
            ->get()
            ->contains(fn (Charge $charge) => $charge->isSettled());

        if ($settled && $membership->activated_at === null) {
            $membership->forceFill(['activated_at' => now()])->save();
        } elseif (! $settled && $membership->activated_at !== null) {
            $membership->forceFill(['activated_at' => null])->save();
        }
    }

    private static function refuseIfCancelled(Charge $charge): void
    {
        if ($charge->isCancelled()) {
            throw ValidationException::withMessages(['reason' => 'This charge is cancelled.']);
        }
    }

    private static function log(Charge $charge, ?User $actor, string $action, ?int $amount = null, ?string $note = null, ?Payment $payment = null): void
    {
        AccountEvent::query()->create([
            'user_id' => $charge->user_id,
            'actor_id' => $actor?->id,
            'charge_id' => $charge->id,
            'payment_id' => $payment?->id,
            'action' => $action,
            'amount' => $amount,
            'note' => $note,
        ]);
    }
}
