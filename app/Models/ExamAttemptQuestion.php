<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A question as it was set in one attempt: its own copy of the wording and
 * of the options in the order shown, the right one, and the answer given.
 *
 * @property int $id
 * @property int $exam_attempt_id
 * @property int|null $exam_question_id
 * @property int $position
 * @property string $area
 * @property string|null $scenario
 * @property string $question
 * @property list<string> $options
 * @property int $correct_option
 * @property int|null $answer
 * @property CarbonImmutable|null $answered_at
 */
#[Fillable(['exam_attempt_id', 'exam_question_id', 'position', 'area', 'scenario', 'question', 'options', 'correct_option'])]
class ExamAttemptQuestion extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['options' => 'array', 'answered_at' => 'datetime'];
    }
}
