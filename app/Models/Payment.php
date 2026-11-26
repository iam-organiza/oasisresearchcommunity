<?php

namespace App\Models;

use App\Enums\Account\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Money received against one charge, with its receipt number. A payment
 * recorded by mistake is voided with a reason, never deleted.
 *
 * @property int $id
 * @property int $charge_id
 * @property int $user_id
 * @property string $receipt_number
 * @property int $amount
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property CarbonImmutable $paid_on
 * @property string|null $note
 * @property int|null $recorded_by
 * @property CarbonImmutable|null $voided_at
 * @property string|null $void_reason
 * @property CarbonImmutable $created_at
 */
#[Fillable(['charge_id', 'user_id', 'receipt_number', 'amount', 'method', 'reference', 'paid_on', 'note', 'recorded_by'])]
class Payment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'paid_on' => 'date',
            'voided_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /**
     * The next receipt number in a year, as in OASIS/RCT/2026/00042. Call
     * inside a transaction.
     */
    public static function nextReceiptNumber(int $year): string
    {
        $prefix = "OASIS/RCT/{$year}/";

        $last = DB::table('payments')
            ->where('receipt_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('receipt_number')
            ->value('receipt_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
