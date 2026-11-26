<?php

namespace App\Models;

use App\Enums\Membership\Grade;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One sitting of the membership exam. The clock is the server's: it ends
 * at `ends_at` whether or not the candidate is still on the page.
 *
 * @property int $id
 * @property int $user_id
 * @property int $membership_application_id
 * @property int|null $charge_id
 * @property int $number attempt 1, 2 or 3 on its exam fee
 * @property Grade $grade the grade sat for
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable|null $finished_at
 * @property int $total
 * @property int|null $correct
 * @property float|null $percent
 * @property bool|null $passed
 * @property array<string, array{label: string, correct: int, total: int}>|null $area_scores
 */
#[Fillable(['user_id', 'membership_application_id', 'charge_id', 'number', 'grade', 'started_at', 'ends_at', 'total'])]
class ExamAttempt extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade' => Grade::class,
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'finished_at' => 'datetime',
            'percent' => 'float',
            'passed' => 'boolean',
            'area_scores' => 'array',
        ];
    }

    /**
     * @return HasMany<ExamAttemptQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(ExamAttemptQuestion::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<MembershipApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(MembershipApplication::class, 'membership_application_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    /**
     * Still open: not finished and the time not up.
     */
    public function isRunning(): bool
    {
        return ! $this->isFinished() && $this->ends_at->isFuture();
    }
}
