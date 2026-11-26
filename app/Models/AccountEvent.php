<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change to a person's account: who added, waived, paid, voided or
 * cancelled what, when, and why.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $actor_id
 * @property int|null $charge_id
 * @property int|null $payment_id
 * @property string $action
 * @property int|null $amount
 * @property string|null $note
 * @property CarbonImmutable $created_at
 */
#[Fillable(['user_id', 'actor_id', 'charge_id', 'payment_id', 'action', 'amount', 'note'])]
class AccountEvent extends Model
{
    public const UPDATED_AT = null;

    public const LABELS = [
        'charge_added' => 'Added a charge',
        'charge_cancelled' => 'Cancelled a charge',
        'waiver_set' => 'Waived a charge',
        'waiver_removed' => 'Removed a waiver',
        'payment_recorded' => 'Recorded a payment',
        'payment_voided' => 'Voided a payment',
        'upgrade_applied' => 'Moved up a grade',
        'lapsed' => 'Membership lapsed: subscription unpaid 30 days after it was due',
        'reinstated' => 'Membership active again: overdue subscription paid',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<Charge, $this>
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->action] ?? $this->action;
    }
}
