<?php

namespace App\Models;

use App\Enums\Account\ChargeKind;
use App\Enums\Account\WaiverType;
use App\Enums\Membership\Grade;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One fee on a person's account: an exam fee, an induction fee or a year's
 * subscription. The amount is fixed when the charge is added, so a later
 * change to the fees does not touch it. A waiver takes some or all of it
 * off; payments go against what is left.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $membership_id
 * @property ChargeKind $kind
 * @property int|null $year
 * @property CarbonImmutable|null $due_on
 * @property Grade|null $grade
 * @property int $amount
 * @property WaiverType|null $waiver_type
 * @property int|null $waiver_value
 * @property int $waived_amount
 * @property string|null $waiver_reason
 * @property int|null $waived_by
 * @property CarbonImmutable|null $waived_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancel_reason
 * @property CarbonImmutable|null $reminded_due_at
 * @property CarbonImmutable|null $reminded_final_at
 * @property CarbonImmutable|null $applied_at
 * @property CarbonImmutable $created_at
 */
#[Fillable(['user_id', 'membership_id', 'kind', 'year', 'due_on', 'grade', 'amount', 'created_by'])]
class Charge extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ChargeKind::class,
            'grade' => Grade::class,
            'waiver_type' => WaiverType::class,
            'due_on' => 'date',
            'waived_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reminded_due_at' => 'datetime',
            'reminded_final_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function waivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }

    /**
     * Every payment, voided ones included, oldest first.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_on')->orderBy('id');
    }

    public function label(): string
    {
        return match ($this->kind) {
            ChargeKind::Subscription => $this->due_on ? "Subscription due {$this->due_on->format('j M Y')}" : "Subscription {$this->year}",
            ChargeKind::Upgrade => 'Upgrade to '.($this->grade?->label() ?? 'a higher grade'),
            ChargeKind::Induction => $this->grade ? "Induction fee, {$this->grade->label()}" : 'Induction fee',
            ChargeKind::Exam => 'Exam fee',
            ChargeKind::Postage => 'Induction pack postage',
        };
    }

    /**
     * The last day of the 30 days' grace after a subscription falls due
     * (OASIS, 2026-11-27). A subscription added after its due date, for
     * example on catching up, gets its 30 days from when it was added.
     */
    public function graceEndsOn(): ?CarbonImmutable
    {
        if ($this->due_on === null) {
            return null;
        }
        $start = $this->due_on->max($this->created_at->startOfDay());

        return $start->addDays(Membership::GRACE_DAYS);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function paidAmount(): int
    {
        return (int) $this->payments->whereNull('voided_at')->sum('amount');
    }

    /**
     * What is still to pay. Nothing for a cancelled charge.
     */
    public function balance(): int
    {
        return $this->isCancelled() ? 0 : max(0, $this->amount - $this->waived_amount - $this->paidAmount());
    }

    public function isSettled(): bool
    {
        return ! $this->isCancelled() && $this->balance() === 0;
    }

    /**
     * Due, Part paid, Paid, Waived or Cancelled.
     */
    public function status(): string
    {
        return match (true) {
            $this->isCancelled() => 'Cancelled',
            $this->waived_amount >= $this->amount => 'Waived',
            $this->balance() === 0 => 'Paid',
            $this->paidAmount() > 0 || $this->waived_amount > 0 => 'Part paid',
            default => 'Due',
        };
    }

    public function waiverDescription(): ?string
    {
        if ($this->waiver_type === null) {
            return null;
        }

        return match ($this->waiver_type) {
            WaiverType::Full => 'Waived in full',
            WaiverType::Amount => '₦'.number_format($this->waived_amount).' waived',
            WaiverType::Percent => "{$this->waiver_value}% waived (₦".number_format($this->waived_amount).')',
        };
    }
}
