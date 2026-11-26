<?php

namespace App\Models;

use App\Membership\ApplicationSteps;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One multiple-choice question in the exam bank. It may open with a short
 * scenario. `options` holds 2 to 6 answers and `correct_option` the index
 * of the right one (0 = A).
 *
 * @property int $id
 * @property string $area
 * @property string|null $scenario
 * @property string $question
 * @property list<string> $options
 * @property int $correct_option
 * @property string|null $explanation
 * @property CarbonImmutable|null $retired_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['area', 'scenario', 'question', 'options', 'correct_option', 'explanation', 'created_by', 'updated_by'])]
class ExamQuestion extends Model
{
    /** Questions per area the bank should have, so retakes don't repeat (proposed to OASIS 2026-11-27). */
    public const TARGET_PER_AREA = 30;

    public const MAX_OPTIONS = 6;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'correct_option' => 'integer',
            'retired_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<ExamQuestion>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('retired_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function areaLabel(): string
    {
        return ApplicationSteps::AREAS[$this->area]['label'] ?? $this->area;
    }

    public function isAdvanced(): bool
    {
        return ApplicationSteps::AREAS[$this->area]['advanced'] ?? false;
    }

    public static function letter(int $index): string
    {
        return chr(ord('A') + $index);
    }
}
