<?php

namespace App\Http\Controllers\Membership;

use App\Account\ExchangeRate;
use App\Enums\Membership\ApplicationStatus;
use App\Enums\Membership\DocumentKind;
use App\Enums\Membership\FellowBasis;
use App\Enums\Membership\Grade;
use App\Enums\Membership\InductionEvent;
use App\Enums\Membership\Route;
use App\Http\Controllers\Controller;
use App\Membership\ApplicationSteps;
use App\Membership\ApproveApplication;
use App\Membership\MembershipMail;
use App\Models\MembershipApplication;
use App\Models\Region;
use App\Models\User;
use App\Notifications\Membership\ApplicationApproved;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The membership application, one step at a time. Each step saves on its
 * own; the review step submits once every earlier step is complete.
 */
class ApplicationController extends Controller
{
    /**
     * Start an application, or carry on with the one in progress.
     */
    public function start(Request $request): RedirectResponse
    {
        // Someone already on the register has nothing to apply for.
        if ($request->user()->membership()->exists()) {
            return to_route('membership.show');
        }

        $application = $this->editableApplication($request->user());

        if (! $application) {
            $latest = $request->user()->latestApplication;

            // One application at a time; a declined applicant may apply again.
            if ($latest && $latest->status !== ApplicationStatus::Declined) {
                return to_route('membership.show');
            }

            $application = $request->user()->membershipApplications()->create();
        }

        return to_route('membership.apply.edit', $application->missingSteps()[0] ?? 'review');
    }

    public function edit(Request $request, string $step): Response|RedirectResponse
    {
        abort_unless(ApplicationSteps::exists($step), 404);

        $application = $this->editableApplication($request->user());

        if (! $application) {
            return to_route('membership.show');
        }

        $application->load(['publications', 'documents', 'state']);

        return Inertia::render('membership/apply', [
            'step' => $step,
            'steps' => collect(ApplicationSteps::STEPS)->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'complete' => $key === 'review' ? false : $application->isComplete($key),
            ])->values(),
            'missingSteps' => $application->missingSteps(),
            'application' => $this->formData($application),
            'documents' => $application->documents->map(fn ($document) => [
                'id' => $document->id,
                'kind' => $document->kind->value,
                'kind_label' => $document->kind->label(),
                'name' => $document->original_name,
                'size' => $document->size,
                'url' => route('membership.documents.show', $document),
            ]),
            'options' => $this->options(),
        ]);
    }

    public function update(Request $request, string $step): RedirectResponse
    {
        abort_unless(ApplicationSteps::exists($step) && ! in_array($step, ['documents', 'review'], true), 404);

        $application = $this->editableApplication($request->user());
        abort_unless($application !== null, 403);

        $data = $request->validate(ApplicationSteps::rules($step), ApplicationSteps::messages());

        DB::transaction(function () use ($application, $step, $data): void {
            $fields = Arr::except($data, ['publications']);

            // Fellow-only answers are cleared when another grade is chosen.
            if ($step === 'grade' && ($data['grade'] ?? null) !== Grade::Fellow->value) {
                $fields['fellow_basis'] = null;
                $fields['grade_evidence'] = null;
            }

            if ($step === 'route') {
                if (($data['route'] ?? null) !== Route::Exam->value) {
                    $fields['exam_status'] = null;
                }
                if (($fields['exam_status'] ?? null) !== 'passed') {
                    $fields['exam_passed_on'] = null;
                }
                if (($data['induction_status'] ?? null) !== 'attended') {
                    $fields['induction_attended_on'] = null;
                }
                if (($data['route'] ?? null) !== Route::Exemption->value) {
                    $fields['exemption_reason'] = null;
                    $fields['exemption_links'] = null;
                } else {
                    $fields['exemption_links'] = array_values($data['exemption_links'] ?? []);
                }
            }

            if ($step === 'experience') {
                // Students are not offered the advanced areas; drop any that slip through.
                $fields['claimed_areas'] = array_values(array_intersect(
                    $data['claimed_areas'],
                    ApplicationSteps::areasFor($application->grade),
                ));

                $application->publications()->delete();
                foreach (array_values($data['publications'] ?? []) as $position => $publication) {
                    $application->publications()->create([...$publication, 'position' => $position]);
                }
            }

            // A different referee has not answered yet; ask them on the next submission.
            if ($step === 'referee' && ($fields['referee_email'] ?? null) !== $application->referee_email) {
                $application->referee_status = null;
                $application->referee_responded_at = null;
            }

            $application->fill($fields);
            $application->markComplete($step);
            $application->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Saved.']);

        return $request->boolean('stay')
            ? to_route('membership.apply.edit', $step)
            : to_route('membership.apply.edit', ApplicationSteps::next($step) ?? 'review');
    }

    public function submit(Request $request): RedirectResponse
    {
        $application = $this->editableApplication($request->user());
        abort_unless($application !== null, 403);

        $data = $request->validate(ApplicationSteps::rules('review'), ApplicationSteps::messages());

        if ($missing = $application->missingSteps()) {
            throw ValidationException::withMessages([
                'declaration' => 'Finish these steps first: '
                    .collect($missing)->map(fn (string $step) => ApplicationSteps::STEPS[$step])->implode(', ').'.',
            ]);
        }

        $application->fill([
            'directory_consent' => $data['directory_consent'] ?? false,
            'keep_documents_two_years' => $data['keep_documents_two_years'] ?? false,
        ]);
        $resubmitting = $application->status === ApplicationStatus::MoreInformation;
        $application->declared_at = now();
        $application->submitted_at = now();
        $application->status = ApplicationStatus::Submitted;
        if ($application->route === Route::Exemption && $application->exemption_status !== 'approved') {
            $application->exemption_status = 'requested';
        }
        $application->save();
        $application->log($resubmitting ? 'resubmitted' : 'submitted', $request->user(), $application->grade);
        MembershipMail::submitted($application, $resubmitting ? 'resubmitted' : 'submitted');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Application submitted.']);

        return to_route('membership.show');
    }

    /**
     * The applicant takes the grade the registrar recommended and joins at it.
     */
    public function accept(Request $request): RedirectResponse
    {
        $application = $request->user()->latestApplication;
        abort_unless($application && $application->status === ApplicationStatus::Recommended && $application->decided_grade, 403);

        $membership = ApproveApplication::handle($application, $application->decided_grade, $request->user(), 'accepted');
        MembershipMail::send($request->user(), new ApplicationApproved($membership));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Welcome to OASIS Research Community.']);

        return to_route('membership.show');
    }

    /**
     * The applicant asks for the grade they applied for, with their reasons
     * and any further evidence. The application goes back to the registrar.
     */
    public function appeal(Request $request): RedirectResponse
    {
        $application = $request->user()->latestApplication;
        abort_unless($application && $application->status === ApplicationStatus::Recommended, 403);

        $data = $request->validate([
            'appeal_reason' => ['required', 'string', 'min:30', 'max:2000'],
            'files' => ['array', 'max:3'],
            'files.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:'.ApplicationDocumentController::MAX_KILOBYTES],
        ], [
            'appeal_reason.required' => 'Explain why you should be awarded the grade you applied for.',
            'appeal_reason.min' => 'Give a fuller reason (at least 30 characters).',
            'files.max' => 'Add up to 3 files with your appeal.',
            'files.*.mimes' => 'Upload PDF, JPG or PNG files.',
            'files.*.max' => 'Each file must be 5 MB or smaller.',
        ]);

        $room = ApplicationDocumentController::MAX_DOCUMENTS - $application->documents()->count();
        if (count($data['files'] ?? []) > $room) {
            throw ValidationException::withMessages([
                'files' => "Your application already has most of its {$this->maxDocuments()} files; add up to {$room} more.",
            ]);
        }

        DB::transaction(function () use ($application, $data, $request): void {
            foreach ($data['files'] ?? [] as $file) {
                $application->documents()->create([
                    'kind' => DocumentKind::Other->value,
                    'path' => $file->store("applications/{$application->id}", 'local'),
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'mime_type' => (string) $file->getMimeType(),
                    'size' => (int) $file->getSize(),
                ]);
            }

            $application->appeal_reason = $data['appeal_reason'];
            $application->status = ApplicationStatus::Submitted;
            $application->submitted_at = now();
            $application->save();
            $application->log('appealed', $request->user(), $application->grade, $data['appeal_reason']);
        });
        MembershipMail::submitted($application, 'appealed');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Appeal sent to the Registrar.']);

        return to_route('membership.show');
    }

    private function maxDocuments(): int
    {
        return ApplicationDocumentController::MAX_DOCUMENTS;
    }

    private function editableApplication(User $user): ?MembershipApplication
    {
        $application = $user->latestApplication;

        return $application && $application->status->isEditable() ? $application : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(MembershipApplication $application): array
    {
        return [
            'title' => $application->title ?? '',
            'phone' => $application->phone ?? '',
            'state_id' => $application->state_id ? (string) $application->state_id : '',
            'city' => $application->city ?? '',
            'grade' => $application->grade->value ?? '',
            'fellow_basis' => $application->fellow_basis->value ?? '',
            'grade_evidence' => $application->grade_evidence ?? '',
            'highest_qualification' => $application->highest_qualification ?? '',
            'current_role' => $application->current_role ?? '',
            'institution' => $application->institution ?? '',
            'department' => $application->department ?? '',
            'years_experience' => $application->years_experience !== null ? (string) $application->years_experience : '',
            'publications' => $application->publications->map(fn ($publication) => [
                'title' => $publication->title,
                'venue' => $publication->venue,
                'year' => (string) $publication->year,
                'link' => $publication->link ?? '',
            ])->values(),
            'claimed_areas' => $application->claimed_areas ?? [],
            'route' => $application->route->value ?? '',
            'exemption_reason' => $application->exemption_reason ?? '',
            'exemption_links' => $application->exemption_links ?? [],
            'exam_status' => $application->exam_status ?? '',
            'exam_passed_on' => $application->exam_passed_on?->toDateString() ?? '',
            'induction_event' => $application->induction_event->value ?? '',
            'induction_status' => $application->induction_status ?? '',
            'induction_attended_on' => $application->induction_attended_on?->toDateString() ?? '',
            'referee_name' => $application->referee_name ?? '',
            'referee_email' => $application->referee_email ?? '',
            'referee_relationship' => $application->referee_relationship ?? '',
            'referee_membership_number' => $application->referee_membership_number ?? '',
            'directory_consent' => $application->directory_consent,
            'keep_documents_two_years' => $application->keep_documents_two_years,
            'state_label' => $application->state?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        $pairs = fn (array $items) => collect($items)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values();
        $cases = fn (array $cases) => collect($cases)->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()])->values();

        return [
            'regions' => Region::query()->orderBy('name')->with('states:id,region_id,name')->get(['id', 'name'])
                ->map(fn (Region $region) => [
                    'name' => $region->name,
                    'states' => $region->states->map(fn ($state) => ['value' => (string) $state->id, 'label' => $state->name])->values(),
                ]),
            'grades' => collect(Grade::cases())->map->toOption()->values(),
            'rateAttribution' => ExchangeRate::ATTRIBUTION_URL,
            'fellowBases' => $cases(FellowBasis::cases()),
            'qualifications' => $pairs(ApplicationSteps::QUALIFICATIONS),
            'areas' => collect(ApplicationSteps::AREAS)->map(fn (array $area, string $key) => [
                'value' => $key,
                'label' => $area['label'],
                'advanced' => $area['advanced'],
            ])->values(),
            'routes' => $cases(Route::cases()),
            'examStatuses' => $pairs(ApplicationSteps::EXAM_STATUSES),
            'inductionEvents' => $cases(InductionEvent::cases()),
            'inductionStatuses' => $pairs(ApplicationSteps::INDUCTION_STATUSES),
            'documentKinds' => $cases(DocumentKind::cases()),
            'maxDocuments' => ApplicationDocumentController::MAX_DOCUMENTS,
            'maxDocumentMb' => ApplicationDocumentController::MAX_KILOBYTES / 1024,
            'maxPublications' => ApplicationSteps::MAX_PUBLICATIONS,
        ];
    }
}
