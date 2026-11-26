<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to pay a charge through Paystack.
 *
 * Statuses: pending (sent to Paystack), paid (confirmed and recorded),
 * failed (Paystack says it did not go through), abandoned (never finished
 * within a day), needs_attention (Paystack took the money but it could not
 * be applied, for example the charge was settled meanwhile; the Registrar
 * is told, to refund or apply it by hand).
 *
 * @property int $id
 * @property int $user_id
 * @property int $charge_id
 * @property string $reference
 * @property string $currency
 * @property int $amount naira credited to the charge
 * @property int $fee_minor Paystack's charge, in kobo or cents
 * @property int $total_minor what Paystack charges, in kobo or cents
 * @property float|null $naira_per_dollar
 * @property string $status
 * @property int|null $payment_id
 * @property string|null $paystack_id
 * @property string|null $channel
 * @property string|null $gateway_response
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $checked_at
 * @property CarbonImmutable $created_at
 */
#[Fillable(['user_id', 'charge_id', 'reference', 'currency', 'amount', 'fee_minor', 'total_minor', 'naira_per_dollar'])]
class OnlinePayment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'naira_per_dollar' => 'float',
            'paid_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Charge, $this>
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * An amount in kobo or cents as people read it: ₦152,000 or US$109.42.
     */
    public function money(int $minor): string
    {
        return $this->currency === 'USD'
            ? 'US$'.number_format($minor / 100, 2)
            : '₦'.number_format($minor / 100, $minor % 100 === 0 ? 0 : 2);
    }
}
