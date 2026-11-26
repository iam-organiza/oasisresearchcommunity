<?php

namespace App\Exam;

use App\Membership\ApplicationSteps;
use App\Models\ExamQuestion;
use Illuminate\Validation\Rule;

/**
 * What a question in the bank must have, for the form and the spreadsheet
 * import alike.
 */
final class QuestionRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'area' => ['required', Rule::in(array_keys(ApplicationSteps::AREAS))],
            'scenario' => ['nullable', 'string', 'max:3000'],
            'question' => ['required', 'string', 'max:2000'],
            'options' => ['required', 'array', 'min:2', 'max:'.ExamQuestion::MAX_OPTIONS],
            'options.*' => ['required', 'string', 'max:500', 'distinct:ignore_case'],
            'correct_option' => ['required', 'integer', 'min:0', 'lt:'.ExamQuestion::MAX_OPTIONS],
            'explanation' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'area.required' => 'Choose the research area.',
            'area.in' => 'Choose one of the 14 research areas.',
            'question.required' => 'Write the question.',
            'options.min' => 'Give at least two options.',
            'options.max' => 'Give at most six options.',
            'options.*.required' => 'Fill in every option, or remove the empty one.',
            'options.*.distinct' => 'Two options say the same thing.',
            'correct_option.required' => 'Mark the right answer.',
            'correct_option.min' => 'Mark the right answer.',
        ];
    }
}
