<?php

namespace App\Models;

use App\Enums\Membership\Grade;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A member on the register: their number, grade and chapter. They are an
 * active member once their induction fee is settled (paid, waived, or a
 * mix), which App\Account\Account keeps in activated_at.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $membership_application_id
 * @property string|null $number issued when the induction fee is settled
 * @property Grade $grade
 * @property CarbonImmutable|null $grade_since
 * @property int|null $state_id
 * @property CarbonImmutable $joined_on
 * @property CarbonImmutable|null $activated_at
 * @property CarbonImmutable|null $lapsed_at
 * @property string|null $induction_delivery 'ceremony' or 'post'
 * @property string|null $delivery_address
 * @property string|null $delivery_city
 * @property int|null $delivery_state_id
 * @property string|null $delivery_phone
 * @property CarbonImmutable|null $pack_posted_at
 * @property CarbonImmutable|null $pack_delivered_at
 */
#[Fillable(['user_id', 'membership_application_id', 'number', 'grade', 'grade_since', 'state_id', 'joined_on', 'activated_at', 'lapsed_at', 'recorded_by'])]
class Membership extends Model
{
    /** Days after a subscription falls due before an unpaid member lapses (OASIS, 2026-11-27). */
    public const GRACE_DAYS = 30;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade' => Grade::class,
            'joined_on' => 'date',
            'grade_since' => 'date',
            'activated_at' => 'datetime',
            'lapsed_at' => 'datetime',
            'pack_posted_at' => 'datetime',
            'pack_delivered_at' => 'datetime',
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
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * Where a posted induction pack goes; empty once it is delivered.
     *
     * @return BelongsTo<State, $this>
     */
    public function deliveryState(): BelongsTo
    {
        return $this->belongsTo(State::class, 'delivery_state_id');
    }

    /**
     * In good standing: induction fee settled and no subscription more than
     * 30 days overdue. A lapsed member loses the active status and most
     * member features until they pay (OASIS, 2026-11-27).
     */
    public function isActive(): bool
    {
        return $this->activated_at !== null && $this->lapsed_at === null;
    }

    public function isLapsed(): bool
    {
        return $this->activated_at !== null && $this->lapsed_at !== null;
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->activated_at === null => 'Awaiting induction fee',
            $this->lapsed_at !== null => 'Lapsed',
            default => 'Active',
        };
    }

    /**
     * Whole years on the current grade, for upgrade prices.
     */
    public function yearsOnGrade(): int
    {
        return (int) ($this->grade_since ?? $this->joined_on)->diffInYears(now(), absolute: true);
    }

    /**
     * The next number for a grade in a year, as in OASIS/FEL/2026/0012.
     * Each grade counts up on its own every year. Call inside a transaction.
     */
    public static function nextNumber(Grade $grade, int $year): string
    {
        $prefix = "OASIS/{$grade->code()}/{$year}/";

        $last = DB::table('memberships')
            ->where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('number')
            ->value('number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
