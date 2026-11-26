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
 * @property string $number
 * @property Grade $grade
 * @property int|null $state_id
 * @property CarbonImmutable $joined_on
 * @property CarbonImmutable|null $activated_at
 */
#[Fillable(['user_id', 'membership_application_id', 'number', 'grade', 'state_id', 'joined_on', 'activated_at', 'recorded_by'])]
class Membership extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade' => Grade::class,
            'joined_on' => 'date',
            'activated_at' => 'datetime',
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

    public function isActive(): bool
    {
        return $this->activated_at !== null;
    }

    public function statusLabel(): string
    {
        return $this->isActive() ? 'Active' : 'Awaiting induction fee';
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
