<?php

namespace App\Membership;

use App\Enums\Membership\Grade;
use App\Enums\Membership\InductionEvent;
use App\Enums\Membership\Route;
use Illuminate\Validation\Rule;

/**
 * The six steps of the membership application and the rules for each.
 * (A referee is no longer asked for when applying: OASIS, 2026-11-27.)
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
        'review' => 'Review',
    ];

    public const QUALIFICATIONS = [
        'secondary' => 'Secondary school',
        'ond' => 'OND or a similar diploma',
        'hnd' => 'HND',
        'bachelors' => "Bachelor's degree",
        'masters' => "Master's degree",
        'phd' => 'PhD or other doctorate',
        'other' => 'Other',
    ];

    /**
     * The kind of work someone does. Every option except "student" and
     * "other" is a role that involves research for the Fellow criteria;
     * public service with research was added by OASIS on 2026-11-27.
     */
    public const ROLE_TYPES = [
        'lecturer' => 'Lecturer or academic',
        'researcher' => 'Researcher or research officer',
        'consultant' => 'Research consultant',
        'ngo' => 'NGO or development work that involves research',
        'public_service' => 'Public service that involves research',
        'private' => 'Private-sector professional whose work involves research (for example a lawyer or doctor)',
        'student' => 'Student',
        'other' => 'Other',
    ];

    /**
     * Whether someone is studying now, and for what.
     */
    public const STUDYING = [
        'no' => 'No',
        'undergraduate' => 'Yes, for a first degree, or at a college or polytechnic',
        'masters' => "Yes, for a master's degree",
        'doctorate' => 'Yes, for a doctorate',
    ];

    /**
     * The research areas the membership exam covers (OASIS, 2026-11-26).
     * Applicants tick the ones they have experience in, and the exam will
     * draw its questions from them. Advanced areas are not offered to
     * Student Members.
     *
     * @var array<string, array{label: string, advanced: bool}>
     */
    public const AREAS = [
        'problem_identification' => ['label' => 'Identifying research problems', 'advanced' => false],
        'objectives' => ['label' => 'Setting research objectives', 'advanced' => false],
        'literature_review' => ['label' => 'Literature review', 'advanced' => false],
        'methodology' => ['label' => 'Research methodology', 'advanced' => false],
        'data_collection' => ['label' => 'Data and data collection', 'advanced' => false],
        'quantitative_analysis' => ['label' => 'Quantitative data analysis', 'advanced' => false],
        'qualitative_analysis' => ['label' => 'Qualitative data analysis', 'advanced' => false],
        'communication' => ['label' => 'Discussing and communicating findings', 'advanced' => false],
        'report_types' => ['label' => 'Kinds of research reports, journals and journal articles', 'advanced' => false],
        'editorial' => ['label' => 'The journal editorial process', 'advanced' => true],
        'reviewing' => ['label' => 'Reviewing for journals', 'advanced' => true],
        'supervision' => ['label' => 'Supervising student research', 'advanced' => true],
        'consulting' => ['label' => 'Research consulting', 'advanced' => true],
        'project_management' => ['label' => 'Research project management', 'advanced' => true],
    ];

    /**
     * The area keys open to a grade: everything, except the advanced areas for students.
     *
     * @return list<string>
     */
    public static function areasFor(?Grade $grade): array
    {
        return array_keys(array_filter(
            self::AREAS,
            fn (array $area) => $grade !== Grade::Student || ! $area['advanced'],
        ));
    }

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
            ],
            'experience' => [
                'highest_qualification' => ['required', Rule::in(array_keys(self::QUALIFICATIONS))],
                'current_role' => ['required', 'string', 'max:150'],
                'role_type' => ['required', Rule::in(array_keys(self::ROLE_TYPES))],
                'currently_studying' => ['required', Rule::in(array_keys(self::STUDYING))],
                'other_evidence' => ['nullable', 'string', 'max:3000'],
                'institution' => ['required', 'string', 'max:150'],
                'department' => ['nullable', 'string', 'max:150'],
                'years_experience' => ['required', 'integer', 'min:0', 'max:70'],
                'publications' => ['array', 'max:'.self::MAX_PUBLICATIONS],
                'publications.*.title' => ['required', 'string', 'max:300'],
                'publications.*.venue' => ['required', 'string', 'max:200'],
                'publications.*.year' => ['required', 'integer', 'min:1950', 'max:'.now()->year],
                'publications.*.link' => ['nullable', 'url', 'max:500'],
                'claimed_areas' => ['required', 'array', 'min:1'],
                'claimed_areas.*' => [Rule::in(array_keys(self::AREAS))],
            ],
            'route' => [
                'route' => ['required', Rule::enum(Route::class)],
                'exemption_reason' => ['nullable', 'required_if:route,exemption', 'string', 'min:50', 'max:2000'],
                'exemption_links' => ['nullable', 'required_if:route,exemption', 'array', 'max:5'],
                'exemption_links.*' => ['nullable', 'required_if:route,exemption', 'url', 'max:500'],
                'exam_status' => ['nullable', 'required_if:route,exam', Rule::in(array_keys(self::EXAM_STATUSES))],
                'exam_passed_on' => ['nullable', 'required_if:exam_status,passed', 'date', 'before_or_equal:today'],
                'induction_event' => ['required', Rule::enum(InductionEvent::class)],
                'induction_status' => ['required', Rule::in(array_keys(self::INDUCTION_STATUSES))],
                'induction_attended_on' => ['nullable', 'required_if:induction_status,attended', 'date', 'before_or_equal:today'],
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
            'role_type.required' => 'Choose the kind of work you do.',
            'currently_studying.required' => 'Say whether you are studying now.',
            'publications.*.title.required' => 'Give the title of each publication.',
            'publications.*.venue.required' => 'Give the journal, publisher or book for each publication.',
            'publications.*.year.required' => 'Give the year of each publication.',
            'publications.*.link.url' => 'Links must be full web addresses, starting with https://.',
            'exam_status.required_if' => 'Tell us whether you have passed the exam or will take it.',
            'claimed_areas.required' => 'Tick at least one area you have experience in.',
            'claimed_areas.min' => 'Tick at least one area you have experience in.',
            'exemption_reason.required_if' => 'Explain why you should be exempted from the exam.',
            'exemption_reason.min' => 'Give a fuller reason for the exemption (at least 50 characters).',
            'exemption_links.required_if' => 'Add at least one link to public evidence of your work.',
            'exemption_links.*.required_if' => 'Fill in each link, or remove it.',
            'exemption_links.*.url' => 'Links must be full web addresses, starting with https://.',
            'exam_passed_on.required_if' => 'Give the date you passed the exam.',
            'induction_attended_on.required_if' => 'Give the date you attended.',
            'declaration.accepted' => 'Confirm that your answers are true to submit the application.',
        ];
    }
}
