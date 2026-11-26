<?php

namespace App\Membership;

use App\Enums\Membership\FellowBasis;
use App\Enums\Membership\Grade;
use App\Enums\Membership\InductionEvent;
use App\Enums\Membership\Route;
use Illuminate\Validation\Rule;

/**
 * The seven steps of the membership application and the rules for each.
 * Steps save one at a time, so a person can stop and come back later.
 */
final class ApplicationSteps
{
    public const STEPS = [
        'about' => 'About you',
        'grade' => 'Grade',
        'experience' => 'Experience',
        'route' => 'Route',
        'documents' => 'Documents',
        'referee' => 'Referee',
        'review' => 'Review',
    ];

    public const QUALIFICATIONS = [
        'secondary' => 'Secondary school',
        'diploma' => 'Diploma (OND, HND or similar)',
        'bachelors' => "Bachelor's degree",
        'masters' => "Master's degree",
        'phd' => 'PhD or other doctorate',
        'other' => 'Other',
    ];

    /** The five research competencies (company profile 3.1), each rated 1 to 5 if the applicant wishes. */
    public const COMPETENCIES = [
        'knowledge' => 'Research knowledge',
        'report_writing' => 'Research report writing',
        'data' => 'Data collection and analysis',
        'communication' => 'Research communication and publication',
        'ethics' => 'Research ethics',
    ];

    public const EXAM_STATUSES = [
        'passed' => 'I have passed the exam',
        'will_take' => 'I will take the exam when it opens on this website',
    ];

    public const INDUCTION_STATUSES = [
        'will_attend' => 'I will attend',
        'attended' => 'I have attended',
    ];

    public const MAX_PUBLICATIONS = 50;

    public static function exists(string $step): bool
    {
        return array_key_exists($step, self::STEPS);
    }

    /**
     * @return list<string>
     */
    public static function beforeReview(): array
    {
        return array_values(array_diff(array_keys(self::STEPS), ['review']));
    }

    public static function next(string $step): ?string
    {
        $keys = array_keys(self::STEPS);
        $index = array_search($step, $keys, true);

        return $keys[$index + 1] ?? null;
    }

    /**
     * Validation for the fields saved by a step. Documents upload on their own.
     *
     * @return array<string, mixed>
     */
    public static function rules(string $step): array
    {
        return match ($step) {
            'about' => [
                'title' => ['nullable', 'string', 'max:36'],
                'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9 +()-]{7,20}$/'],
                'state_id' => ['required', 'integer', Rule::exists('states', 'id')],
                'city' => ['required', 'string', 'max:100'],
            ],
            'grade' => [
                'grade' => ['required', Rule::enum(Grade::class)],
                'fellow_basis' => ['nullable', 'required_if:grade,fellow', Rule::enum(FellowBasis::class)],
                'grade_evidence' => ['nullable', 'required_if:grade,fellow', 'string', 'max:2000'],
            ],
            'experience' => [
                'highest_qualification' => ['required', Rule::in(array_keys(self::QUALIFICATIONS))],
                'current_role' => ['required', 'string', 'max:150'],
                'institution' => ['required', 'string', 'max:150'],
                'department' => ['nullable', 'string', 'max:150'],
                'years_experience' => ['required', 'integer', 'min:0', 'max:70'],
                'publications' => ['array', 'max:'.self::MAX_PUBLICATIONS],
                'publications.*.title' => ['required', 'string', 'max:300'],
                'publications.*.venue' => ['required', 'string', 'max:200'],
                'publications.*.year' => ['required', 'integer', 'min:1950', 'max:'.now()->year],
                'publications.*.link' => ['nullable', 'url', 'max:500'],
                'competencies' => ['nullable', 'array'],
                'competencies.*' => ['nullable', 'integer', 'min:1', 'max:5'],
            ],
            'route' => [
                'route' => ['required', Rule::enum(Route::class)],
                'exam_status' => ['nullable', 'required_if:route,exam', Rule::in(array_keys(self::EXAM_STATUSES))],
                'exam_passed_on' => ['nullable', 'required_if:exam_status,passed', 'date', 'before_or_equal:today'],
                'induction_event' => ['required', Rule::enum(InductionEvent::class)],
                'induction_status' => ['required', Rule::in(array_keys(self::INDUCTION_STATUSES))],
                'induction_attended_on' => ['nullable', 'required_if:induction_status,attended', 'date', 'before_or_equal:today'],
            ],
            'referee' => [
                'referee_name' => ['nullable', 'required_with:referee_email,referee_relationship', 'string', 'max:150'],
                'referee_email' => ['nullable', 'required_with:referee_name', 'email', 'max:255'],
                'referee_relationship' => ['nullable', 'required_with:referee_name', 'string', 'max:150'],
                'referee_membership_number' => ['nullable', 'string', 'max:40'],
            ],
            'review' => [
                'directory_consent' => ['boolean'],
                'keep_documents_two_years' => ['boolean'],
                'declaration' => ['accepted'],
            ],
            default => [],
        };
    }

    /**
     * Messages in the site's voice for the rules above.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'phone.required' => 'Enter your phone number.',
            'phone.regex' => 'Enter a phone number using digits, spaces, + or brackets.',
            'city.required' => 'Enter your city or town.',
            'grade.required' => 'Choose the grade you are applying for.',
            'highest_qualification.required' => 'Choose your highest qualification.',
            'current_role.required' => 'Enter your current role and rank.',
            'institution.required' => 'Enter your institution or organisation.',
            'years_experience.required' => 'Enter your years of research experience (0 if none yet).',
            'route.required' => 'Choose how you qualify.',
            'induction_event.required' => 'Choose your induction event.',
            'induction_status.required' => 'Say whether you have attended it.',
            'state_id.required' => 'Choose the state you live in.',
            'fellow_basis.required_if' => 'Choose what your application to Fellow rests on.',
            'grade_evidence.required_if' => 'Tell us briefly about the evidence for Fellow, such as your degree, rank or role.',
            'publications.*.title.required' => 'Give the title of each publication.',
            'publications.*.venue.required' => 'Give the journal, publisher or book for each publication.',
            'publications.*.year.required' => 'Give the year of each publication.',
            'publications.*.link.url' => 'Links must be full web addresses, starting with https://.',
            'exam_status.required_if' => 'Tell us whether you have passed the exam or will take it.',
            'exam_passed_on.required_if' => 'Give the date you passed the exam.',
            'induction_attended_on.required_if' => 'Give the date you attended.',
            'referee_email.required_with' => "Give your referee's email address so we can ask them to confirm.",
            'referee_relationship.required_with' => 'Say how your referee knows you.',
            'referee_name.required_with' => "Give your referee's name.",
            'declaration.accepted' => 'Confirm that your answers are true to submit the application.',
        ];
    }
}
