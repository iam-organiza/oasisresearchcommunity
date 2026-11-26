<?php

namespace App\Account;

use App\Enums\Account\ChargeKind;
use App\Enums\Account\PaymentMethod;
use App\Membership\MembershipMail;
use App\Models\Charge;
use App\Models\OnlinePayment;
use App\Notifications\Account\OnlinePaymentNeedsAttention;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Paying a charge through Paystack. Every amount comes from the database,
 * never from the browser. A payment is recorded only once Paystack confirms
 * a successful transaction with our reference, in our currency, for at
 * least the total we asked for, and each reference is recorded only once.
 *
 * OASIS Journals uses the same Paystack account, so references start with
 * ORC- and carry the site in their metadata. The account's single webhook
 * address is left alone: a payment is checked when the member comes back,
 * and again by `payments:check-pending` for anyone who closes the page.
 */
final class OnlineCheckout
{
    /** Smallest part payment of a subscription, so Paystack's charge stays small beside it. */
    public const MINIMUM_PART = 1000;

    public function __construct(private Paystack $paystack, private ExchangeRate $rates) {}

    /**
     * What the member would pay for `$amount` naira of a charge, in the
     * chosen currency: the fee, Paystack's charge and the total, in kobo or
     * cents. Null for dollars when there is no rate.
     *
     * @return array{currency: string, amount_minor: int, charge_minor: int, total_minor: int, rate: float|null}|null
     */
    public function quote(int $amount, string $currency): ?array
    {
        if ($currency === 'USD') {
            $dollars = $this->rates->dollars($amount);
            if ($dollars === null) {
                return null;
            }
            $total = Paystack::grossUp($dollars * 100, 'USD');

            return ['currency' => 'USD', 'amount_minor' => $dollars * 100, 'charge_minor' => $total - $dollars * 100, 'total_minor' => $total, 'rate' => $this->rates->current()];
        }

        $total = Paystack::grossUp($amount * 100, 'NGN');

        return ['currency' => 'NGN', 'amount_minor' => $amount * 100, 'charge_minor' => $total - $amount * 100, 'total_minor' => $total, 'rate' => null];
    }

    /**
     * Starts paying `$amount` naira of a charge. Exam and induction fees are
     * paid in full; a subscription may be paid in parts. Returns the
     * Paystack page to send the member to.
     */
    public function start(Charge $charge, int $amount, string $currency, string $callbackUrl): string
    {
        $charge->load(['payments', 'user']);

        if (! $this->paystack->enabled()) {
            throw ValidationException::withMessages(['amount' => 'Online payment is not switched on yet.']);
        }

        if ($charge->isCancelled() || $charge->balance() === 0) {
            throw ValidationException::withMessages(['amount' => 'Nothing is left to pay on this charge.']);
        }
        if ($charge->kind !== ChargeKind::Subscription && $amount !== $charge->balance()) {
            throw ValidationException::withMessages(['amount' => 'The '.strtolower($charge->kind->label()).' is paid in one go.']);
        }
        if ($amount > $charge->balance()) {
            throw ValidationException::withMessages(['amount' => 'Only ₦'.number_format($charge->balance()).' is left to pay.']);
        }
        if ($amount < min(self::MINIMUM_PART, $charge->balance())) {
            throw ValidationException::withMessages(['amount' => 'Pay at least ₦'.number_format(self::MINIMUM_PART).' at a time.']);
        }
        if ($currency === 'USD' && ! $this->paystack->usdEnabled()) {
            throw ValidationException::withMessages(['currency' => 'Paying in dollars is not available yet.']);
        }

        $quote = $this->quote($amount, $currency);
        if ($quote === null) {
            throw ValidationException::withMessages(['currency' => "Today's dollar rate is not available. Pay in naira, or try again later."]);
        }

        $attempt = OnlinePayment::query()->create([
            'user_id' => $charge->user_id,
            'charge_id' => $charge->id,
            'reference' => 'ORC-'.Str::upper(Str::random(16)),
            'currency' => $quote['currency'],
            'amount' => $amount,
            'fee_minor' => $quote['charge_minor'],
            'total_minor' => $quote['total_minor'],
            'naira_per_dollar' => $quote['rate'],
        ]);

        try {
            return $this->paystack->initialize(
                $charge->user->email,
                $attempt->total_minor,
                $attempt->currency,
                $attempt->reference,
                $callbackUrl,
                [
                    'site' => 'OASIS Research Community',
                    'charge' => $charge->label(),
                    'charge_id' => $charge->id,
                    'user_id' => $charge->user_id,
                    'cancel_action' => route('membership.account'),
                ],
            );
        } catch (Throwable $exception) {
            report($exception);
            $attempt->forceFill(['status' => 'failed', 'gateway_response' => 'Could not start the payment'])->save();

            throw ValidationException::withMessages(['amount' => 'Paystack could not start the payment. Nothing was taken; please try again in a few minutes.']);
        }
    }

    /**
     * Checks an attempt with Paystack and, once it succeeded, records the
     * payment (which emails the receipt). Safe to call any number of times.
     */
    public function confirm(OnlinePayment $attempt): OnlinePayment
    {
        if ($attempt->status !== 'pending') {
            return $attempt;
        }

        $result = $this->paystack->verify($attempt->reference);
        $attempt->forceFill(['checked_at' => now()])->save();

        if ($result === null) {
            return $attempt;
        }

        if ($result['status'] === 'success') {
            return $this->apply($attempt, $result);
        }

        if (in_array($result['status'], ['failed', 'reversed'], true)) {
            $attempt->forceFill(['status' => 'failed', 'gateway_response' => $result['gateway_response']])->save();
        } elseif ($attempt->created_at->lt(now()->subDay())) {
            // Never finished (abandoned, or Paystack has no such transaction) after a day.
            $attempt->forceFill(['status' => 'abandoned', 'gateway_response' => $result['gateway_response'] ?? $result['status']])->save();
        }

        return $attempt;
    }

    /**
     * @param  array{status: string, reference: string, amount: int, currency: string, id: string, channel: ?string, paid_at: ?string, gateway_response: ?string}  $result
     */
    private function apply(OnlinePayment $attempt, array $result): OnlinePayment
    {
        return DB::transaction(function () use ($attempt, $result): OnlinePayment {
            $attempt = OnlinePayment::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($attempt->status !== 'pending') {
                return $attempt;
            }

            $details = [
                'paystack_id' => $result['id'],
                'channel' => $result['channel'],
                'gateway_response' => $result['gateway_response'],
                'paid_at' => $result['paid_at'] ? CarbonImmutable::parse($result['paid_at']) : now(),
            ];

            $matches = $result['reference'] === $attempt->reference
                && $result['currency'] === $attempt->currency
                && $result['amount'] >= $attempt->total_minor;

            if (! $matches) {
                return $this->needsAttention($attempt, $details, "Paystack confirmed {$result['currency']} {$result['amount']} (minor units) but we asked for {$attempt->currency} {$attempt->total_minor}.");
            }

            try {
                $payment = Account::recordPayment(
                    $attempt->charge,
                    $attempt->amount,
                    PaymentMethod::Online,
                    CarbonImmutable::parse($details['paid_at'])->setTimezone(config('app.timezone')),
                    null,
                    $attempt->reference,
                    'Paid online, '.$attempt->money($attempt->total_minor).' including Paystack\'s charge.',
                );
            } catch (ValidationException $exception) {
                return $this->needsAttention($attempt, $details, collect($exception->errors())->flatten()->first() ?? 'It could not be applied to the charge.');
            }

            $attempt->forceFill([...$details, 'status' => 'paid', 'payment_id' => $payment->id])->save();

            return $attempt;
        });
    }

    /**
     * Paystack took the money but it cannot be applied as it stands, for
     * example the charge was settled by another payment meanwhile. Nothing
     * is recorded; the Registrar is told, to refund or apply it by hand.
     *
     * @param  array<string, mixed>  $details
     */
    private function needsAttention(OnlinePayment $attempt, array $details, string $why): OnlinePayment
    {
        $attempt->forceFill([...$details, 'status' => 'needs_attention', 'gateway_response' => Str::limit($why, 250)])->save();
        MembershipMail::send(MembershipMail::registrars(), new OnlinePaymentNeedsAttention($attempt, $why));

        return $attempt;
    }
}
