<?php

namespace App\Http\Controllers\Membership;

use App\Enums\Membership\ApplicationStatus;
use App\Enums\Membership\DocumentKind;
use App\Enums\Membership\FellowBasis;
use App\Enums\Membership\Grade;
use App\Enums\Membership\InductionEvent;
use App\Enums\Membership\Route;
use App\Http\Controllers\Controller;
use App\Membership\ApplicationSteps;
use App\Models\MembershipApplication;
use App\Models\Region;
use App\Models\User;
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
            }

            if ($step === 'experience') {
                $fields['competencies'] = array_filter(
                    array_intersect_key($data['competencies'] ?? [], ApplicationSteps::COMPETENCIES),
                    fn ($rating) => $rating !== null,
                ) ?: null;

                $application->publications()->delete();
                foreach (array_values($data['publications'] ?? []) as $position => $publication) {
                    $application->publications()->create([...$publication, 'position' => $position]);
                }
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
        $application->declared_at = now();
        $application->submitted_at = now();
        $application->status = ApplicationStatus::Submitted;
        $application->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Application submitted.']);

        return to_route('membership.show');
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
            'competencies' => (object) ($application->competencies ?? []),
            'route' => $application->route->value ?? '',
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
            'fellowBases' => $cases(FellowBasis::cases()),
            'qualifications' => $pairs(ApplicationSteps::QUALIFICATIONS),
            'competencies' => $pairs(ApplicationSteps::COMPETENCIES),
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
