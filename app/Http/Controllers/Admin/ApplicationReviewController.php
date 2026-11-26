<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Membership\ApplicationStatus;
use App\Enums\Membership\Grade;
use App\Enums\Membership\Route;
use App\Http\Controllers\Controller;
use App\Membership\ApplicationSteps;
use App\Membership\ApproveApplication;
use App\Membership\GradeOffer;
use App\Membership\GradeRanking;
use App\Membership\MembershipMail;
use App\Models\ApplicationEvent;
use App\Models\MembershipApplication;
use App\Models\Region;
use App\Notifications\Membership\ApplicationApproved;
use App\Notifications\Membership\ApplicationDeclined;
use App\Notifications\Membership\ExemptionRefused;
use App\Notifications\Membership\MoreInformationNeeded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Registrar's oversight of membership applications (OASIS, 2026-11-27):
 * the site offers each applicant a grade on submitting, and the Registrar
 * looks at the ones flagged for a person (Fellow offers, other evidence,
 * exam exemptions...), answers appeals, and can correct an offer, ask for
 * more, approve or decline.
 */
class ApplicationReviewController extends Controller
{
    /** "check" is a view, not a status: open applications flagged and not yet looked at. */
    private const TABS = ['check', 'submitted', 'offered', 'accepted', 'more_information', 'approved', 'declined'];

    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), self::TABS, true) ? $request->query('status') : 'check';
        $grade = Grade::tryFrom((string) $request->query('grade'));
        $region = $request->integer('region') ?: null;
        $search = trim((string) $request->query('search'));

        $applications = MembershipApplication::query()
            ->when($status === 'check', fn (Builder $query) => $query->toCheck(), fn (Builder $query) => $query->where('status', $status))
            ->with(['user:id,first_name,last_name,email', 'state.region'])
            ->when($grade, fn (Builder $query) => $query->where('grade', $grade->value))
            ->when($region, fn (Builder $query) => $query->whereHas('state', fn (Builder $state) => $state->where('region_id', $region)))
            ->when($search !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy(in_array($status, ['check', 'submitted'], true) ? 'submitted_at' : 'updated_at', in_array($status, ['check', 'submitted'], true) ? 'asc' : 'desc')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (MembershipApplication $application) => [
                'id' => $application->id,
                'name' => $application->user->name,
                'email' => $application->user->email,
                'grade' => $application->grade?->label(),
                'offered' => $application->offered_grade?->label(),
                'flags' => collect($application->flags ?? [])->map(fn (string $flag) => GradeRanking::FLAGS[$flag] ?? $flag)->values(),
                'chapter' => $application->state ? "{$application->state->name}, {$application->state->region->name}" : null,
                'route' => $application->route?->value,
                'exemption_status' => $application->exemption_status,
                'appealed' => $application->appeal_reason !== null && $status === 'submitted',
                'date' => (in_array($status, ['check', 'submitted'], true) ? $application->submitted_at : $application->updated_at)?->format('j M Y'),
            ]);

        $counts = MembershipApplication::query()
            ->selectRaw('status, count(*) as total')
            ->whereIn('status', self::TABS)
            ->groupBy('status')
            ->pluck('total', 'status');
        $counts['check'] = MembershipApplication::query()->toCheck()->count();

        return Inertia::render('admin/applications/index', [
            'applications' => $applications,
            'counts' => collect(self::TABS)->mapWithKeys(fn (string $tab) => [$tab => (int) ($counts[$tab] ?? 0)]),
            'filters' => [
                'status' => $status,
                'grade' => $grade->value ?? '',
                'region' => $region ? (string) $region : '',
                'search' => $search,
            ],
            'grades' => collect(Grade::cases())->map(fn (Grade $grade) => ['value' => $grade->value, 'label' => $grade->label()]),
            'regions' => Region::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Region $region) => ['value' => (string) $region->id, 'label' => $region->name]),
        ]);
    }

    public function show(MembershipApplication $application): Response
    {
        $application->load(['user', 'state.region', 'publications', 'documents', 'events.user', 'membership']);
        $label = fn (array $items, ?string $key) => $key !== null ? ($items[$key] ?? $key) : null;

        return Inertia::render('admin/applications/show', [
            'application' => [
                'id' => $application->id,
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
                'name' => $application->user->name,
                'email' => $application->user->email,
                'title' => $application->title,
                'phone' => $application->phone,
                'chapter' => $application->state ? "{$application->city}, {$application->state->name} ({$application->state->region->name})" : $application->city,
                'grade' => $application->grade?->value,
                'grade_label' => $application->grade?->label(),
                'fellow_basis' => $application->fellow_basis?->label(),
                'grade_evidence' => $application->grade_evidence,
                'qualification' => $label(ApplicationSteps::QUALIFICATIONS, $application->highest_qualification),
                'current_role' => $application->current_role,
                'role_type' => $label(ApplicationSteps::ROLE_TYPES, $application->role_type),
                'studying' => $label(ApplicationSteps::STUDYING, $application->currently_studying),
                'other_evidence' => $application->other_evidence,
                'offered_grade' => $application->offered_grade?->value,
                'offered_label' => $application->offered_grade?->label(),
                'qualifying_label' => $application->qualifying_grade?->label(),
                'offer_reasons' => $application->offer_reasons ?? [],
                'flags' => collect($application->flags ?? [])->map(fn (string $flag) => GradeRanking::FLAGS[$flag] ?? $flag)->values(),
                'reviewed' => $application->reviewed_at !== null,
                'needs_exam' => GradeOffer::needsExam($application),
                'institution' => collect([$application->institution, $application->department])->filter()->implode(', '),
                'years_experience' => $application->years_experience,
                'claimed_areas' => collect($application->claimed_areas ?? [])
                    ->map(fn (string $key) => ApplicationSteps::AREAS[$key]['label'] ?? $key)->values(),
                'publications' => $application->publications->map->only(['title', 'venue', 'year', 'link']),
                'route' => $application->route?->value,
                'route_label' => $application->route?->label(),
                'exam' => $application->route === Route::Exam
                    ? $label(ApplicationSteps::EXAM_STATUSES, $application->exam_status).($application->exam_passed_on ? ' ('.$application->exam_passed_on->format('j M Y').')' : '')
                    : null,
                'exemption_reason' => $application->exemption_reason,
                'exemption_links' => $application->exemption_links ?? [],
                'exemption_status' => $application->exemption_status,
                'induction' => collect([
                    $application->induction_event?->label(),
                    $label(ApplicationSteps::INDUCTION_STATUSES, $application->induction_status),
                    $application->induction_attended_on?->format('j M Y'),
                ])->filter()->implode(' · '),
                'referee' => $application->referee_name ? [
                    'name' => $application->referee_name,
                    'email' => $application->referee_email,
                    'relationship' => $application->referee_relationship,
                    'number' => $application->referee_membership_number,
                    'status' => match ($application->referee_status) {
                        'confirmed' => 'Confirmed they can vouch',
                        'declined' => 'Said they cannot vouch',
                        default => $application->submitted_at ? 'Asked by email, no answer yet' : 'Not asked yet',
                    },
                ] : null,
                'documents' => $application->documents->map(fn ($document) => [
                    'id' => $document->id,
                    'name' => $document->original_name,
                    'kind' => $document->kind->label(),
                    'size' => $document->size,
                    'url' => route('membership.documents.show', $document),
                ]),
                'directory_consent' => $application->directory_consent,
                'keep_documents_two_years' => $application->keep_documents_two_years,
                'submitted_at' => $application->submitted_at?->format('j M Y, g:i a'),
                'appeal_reason' => $application->appeal_reason,
                'decided_grade' => $application->decided_grade?->label(),
                'decision_reason' => $application->decision_reason,
                'decision_improvements' => $application->decision_improvements,
                'registrar_message' => $application->registrar_message,
                'fee_waived' => $application->fee_waived,
                'membership' => $application->membership ? [
                    'number' => $application->membership->number,
                    'grade' => $application->membership->grade->label(),
                    'status' => $application->membership->statusLabel(),
                    'account_url' => route('admin.accounts.show', $application->user_id),
                ] : null,
                'events' => $application->events->map(fn (ApplicationEvent $event) => [
                    'id' => $event->id,
                    'label' => $event->label(),
                    'who' => $event->user?->name,
                    'grade' => $event->grade?->label(),
                    'note' => $event->note,
                    'when' => $event->created_at->format('j M Y, g:i a'),
                ]),
                'can_decide' => $application->status->isOpen() && $application->status !== ApplicationStatus::Draft,
                'can_approve' => $application->status === ApplicationStatus::Accepted && ! GradeOffer::needsExam($application)
                    && $application->exemption_status !== 'requested',
            ],
            'grades' => collect(Grade::cases())->map(fn (Grade $grade) => ['value' => $grade->value, 'label' => $grade->label()]),
        ]);
    }

    /**
     * Let the offer stand, correct it, ask for more, approve, or decline.
     */
    public function decide(Request $request, MembershipApplication $application): RedirectResponse
    {
        abort_unless($application->status->isOpen() && $application->status !== ApplicationStatus::Draft, 403);

        $data = $request->validate([
            'action' => ['required', Rule::in(['confirm', 'correct', 'approve', 'more_information', 'decline'])],
            'grade' => ['nullable', 'required_if:action,correct', Rule::enum(Grade::class)],
            'reason' => ['nullable', 'required_if:action,correct,decline', 'string', 'min:10', 'max:2000'],
            'improvements' => ['nullable', 'required_if:action,decline', 'string', 'min:10', 'max:2000'],
            'message' => ['nullable', 'required_if:action,more_information', 'string', 'min:10', 'max:2000'],
        ], [
            'grade.required_if' => 'Choose the grade to offer.',
            'reason.required_if' => 'Give the reason; the applicant will see it.',
            'improvements.required_if' => 'Say what they can improve or provide evidence of.',
            'message.required_if' => 'Say what more you need from the applicant.',
        ]);

        $registrar = $request->user();

        switch ($data['action']) {
            case 'confirm':
                $application->forceFill(['reviewed_at' => now()])->save();
                $application->log('offer_confirmed', $registrar, $application->offered_grade);
                $this->toast('Marked as checked. The offer stands.');

                return back();

            case 'correct':
                GradeOffer::correct($application, Grade::from((string) $data['grade']), (string) $data['reason'], $registrar);
                $this->toast('Offer corrected. The applicant has been emailed.');

                return back();

            case 'approve':
                if ($application->status !== ApplicationStatus::Accepted || GradeOffer::needsExam($application)) {
                    throw ValidationException::withMessages(['action' => 'Approve once they have accepted an offer that needs no exam (exempted, Certification Programme, or exam passed before).']);
                }
                if ($application->exemption_status === 'requested') {
                    throw ValidationException::withMessages(['action' => 'Decide the exam exemption request first.']);
                }
                $membership = ApproveApplication::handle($application, $application->offered_grade ?? $application->grade, $registrar);
                MembershipMail::send($application->user, new ApplicationApproved($membership));
                $this->toast("Approved. Membership number {$membership->number}.");

                return back();
        }

        $application->decided_by = $registrar->id;
        $application->decided_at = now();

        [$status, $fields, $event, $note, $message] = $data['action'] === 'more_information'
            ? [ApplicationStatus::MoreInformation, ['registrar_message' => $data['message']], 'more_information', (string) $data['message'], 'Sent back to the applicant for more information.']
            : [ApplicationStatus::Declined, ['decision_reason' => $data['reason'], 'decision_improvements' => $data['improvements']], 'declined', "{$data['reason']}\n\n{$data['improvements']}", 'Application declined.'];

        $this->record($application, $status, $fields);
        $application->log($event, $registrar, null, $note);
        MembershipMail::send($application->user, $status === ApplicationStatus::MoreInformation
            ? new MoreInformationNeeded($application)
            : new ApplicationDeclined($application));
        $this->toast($message);

        return back();
    }

    /**
     * Approve or refuse a request to be exempted from the exam. A refused
     * applicant takes the exam instead.
     */
    public function exemption(Request $request, MembershipApplication $application): RedirectResponse
    {
        abort_unless($application->route === Route::Exemption && $application->status->isOpen() && $application->status !== ApplicationStatus::Draft, 403);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'refused'])],
            'note' => ['nullable', 'required_if:decision,refused', 'string', 'max:1000'],
        ], ['note.required_if' => 'Say why the exemption is refused; the applicant will see it.']);

        $application->exemption_status = $data['decision'];
        if ($data['decision'] === 'refused') {
            $application->route = Route::Exam;
            $application->exam_status = 'will_take';
        }
        $application->save();
        $application->log("exemption_{$data['decision']}", $request->user(), null, $data['note'] ?? null);
        GradeOffer::chargeExamFee($application);
        if ($data['decision'] === 'refused') {
            MembershipMail::send($application->user, new ExemptionRefused((string) $data['note']));
        }

        $this->toast($data['decision'] === 'approved' ? 'Exemption approved.' : 'Exemption refused; they will take the exam.');

        return back();
    }

    /**
     * Before approval: waive the induction fee that approval will add, for
     * example for a scholarship. Once they are on the register, waivers are
     * made on their fees and payments page.
     */
    public function feeWaiver(Request $request, MembershipApplication $application): RedirectResponse
    {
        $data = $request->validate(['fee_waived' => ['required', 'boolean']]);

        if ($application->membership()->exists()) {
            return back()->withErrors(['fee_waived' => 'They are on the register now. Waive the fee on their fees and payments page.']);
        }

        $application->fee_waived = $data['fee_waived'];
        $application->save();
        $application->log($data['fee_waived'] ? 'fee_waived' : 'fee_unwaived', $request->user());

        $this->toast($data['fee_waived'] ? 'Induction fee waived.' : 'Induction fee waiver removed.');

        return back();
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function record(MembershipApplication $application, ApplicationStatus $status, array $fields): void
    {
        $application->forceFill([...$fields, 'status' => $status])->save();
    }

    private function toast(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
