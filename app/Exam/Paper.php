<?php

namespace App\Exam;

use App\Enums\Membership\Grade;
use App\Membership\ApplicationSteps;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\MembershipApplication;
use Illuminate\Support\Collection;

/**
 * Sets a paper (OASIS, 2026-11-27): 50 questions, basic and advanced by the
 * grade sat for (Student and Graduate Members 50 basic; Members 40 + 10
 * advanced; Fellows 30 + 20), weighted to the areas the applicant says they
 * know, spread across those areas, and avoiding questions they have had in
 * earlier attempts while the bank allows. Question order and the order of
 * each question's options are shuffled.
 */
final class Paper
{
    public const QUESTIONS = 50;

    public const MINUTES = 60;

    public const PASS_PERCENT = 60;

    /** Advanced questions in a paper, by grade; the rest are basic. */
    public const ADVANCED = [
        'fellow' => 20,
        'member' => 10,
        'graduate' => 0,
        'student' => 0,
    ];

    /**
     * Whether the bank has enough questions in use to set a paper at a grade.
     */
    public static function canSet(Grade $grade): bool
    {
        [$basic, $advanced] = self::split($grade);
        $pool = self::pool();

        return $pool['basic']->count() >= $basic && $pool['advanced']->count() >= $advanced;
    }

    /**
     * The questions for a new attempt, as rows ready to save: the area, the
     * wording, the options in shuffled order and which of them is right.
     *
     * @return array<int, array{exam_question_id: int, position: int, area: string, scenario: string|null, question: string, options: list<string>, correct_option: int}>
     */
    public static function set(MembershipApplication $application, Grade $grade): array
    {
        [$basicCount, $advancedCount] = self::split($grade);
        $pool = self::pool();
        $claimed = $application->claimed_areas ?? [];
        $seen = ExamAttempt::query()
            ->where('user_id', $application->user_id)
            ->join('exam_attempt_questions', 'exam_attempt_questions.exam_attempt_id', '=', 'exam_attempts.id')
            ->pluck('exam_attempt_questions.exam_question_id')
            ->filter()
            ->flip();

        $chosen = self::pick($pool['basic'], $basicCount, $claimed, $seen)
            ->concat(self::pick($pool['advanced'], $advancedCount, $claimed, $seen))
            ->shuffle()
            ->values();

        return $chosen->map(function (ExamQuestion $question, int $index) {
            $order = array_keys($question->options);
            shuffle($order);

            return [
                'exam_question_id' => $question->id,
                'position' => $index + 1,
                'area' => $question->area,
                'scenario' => $question->scenario,
                'question' => $question->question,
                'options' => array_map(fn (int $i) => $question->options[$i], $order),
                'correct_option' => (int) array_search($question->correct_option, $order, true),
            ];
        })->all();
    }

    /**
     * Whether a paper can be set for each grade, with what it needs from the
     * bank (basic and advanced questions) against what the bank has in use.
     *
     * @return list<array{grade: string, label: string, ready: bool, basic: array{have: int, need: int}, advanced: array{have: int, need: int}}>
     */
    public static function readiness(): array
    {
        $pool = self::pool();

        return array_map(function (Grade $grade) use ($pool) {
            [$basic, $advanced] = self::split($grade);

            return [
                'grade' => $grade->value,
                'label' => $grade->label(),
                'ready' => $pool['basic']->count() >= $basic && $pool['advanced']->count() >= $advanced,
                'basic' => ['have' => $pool['basic']->count(), 'need' => $basic],
                'advanced' => ['have' => $pool['advanced']->count(), 'need' => $advanced],
            ];
        }, Grade::cases());
    }

    /**
     * @return array{0: int, 1: int} basic, advanced
     */
    private static function split(Grade $grade): array
    {
        $advanced = self::ADVANCED[$grade->value];

        return [self::QUESTIONS - $advanced, $advanced];
    }

    /**
     * Questions in use, split by whether their area is advanced.
     *
     * @return array{basic: Collection<int, ExamQuestion>, advanced: Collection<int, ExamQuestion>}
     */
    private static function pool(): array
    {
        $all = ExamQuestion::query()->active()->get();
        $advancedAreas = array_keys(array_filter(ApplicationSteps::AREAS, fn (array $area) => $area['advanced']));

        return [
            'basic' => $all->reject(fn (ExamQuestion $question) => in_array($question->area, $advancedAreas, true))->values(),
            'advanced' => $all->filter(fn (ExamQuestion $question) => in_array($question->area, $advancedAreas, true))->values(),
        ];
    }

    /**
     * Takes `$count` questions: first from the areas they claim, a few from
     * each in turn, then from the others; unseen questions before ones they
     * have had.
     *
     * @param  Collection<int, ExamQuestion>  $pool
     * @param  list<string>  $claimed
     * @param  Collection<int|string, int>  $seen
     * @return Collection<int, ExamQuestion>
     */
    private static function pick(Collection $pool, int $count, array $claimed, Collection $seen): Collection
    {
        if ($count === 0) {
            return collect();
        }

        $ordered = $pool->shuffle()->sortBy(fn (ExamQuestion $question) => $seen->has($question->id) ? 1 : 0)->values();
        $mine = $ordered->filter(fn (ExamQuestion $question) => in_array($question->area, $claimed, true));
        $others = $ordered->reject(fn (ExamQuestion $question) => in_array($question->area, $claimed, true));

        return self::roundRobin($mine, $count)
            ->concat(self::roundRobin($others, $count))
            ->unique('id')
            ->take($count)
            ->values();
    }

    /**
     * One question from each area in turn, so no single area takes over.
     *
     * @param  Collection<int, ExamQuestion>  $questions
     * @return Collection<int, ExamQuestion>
     */
    private static function roundRobin(Collection $questions, int $count): Collection
    {
        $byArea = $questions->groupBy('area')->map->values();
        $result = collect();
        $round = 0;

        while ($result->count() < $count && $byArea->contains(fn (Collection $list) => $list->has($round))) {
            foreach ($byArea as $list) {
                if ($list->has($round)) {
                    $result->push($list[$round]);
                }
            }
            $round++;
        }

        return $result;
    }
}
