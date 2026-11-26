<?php

namespace App\Http\Controllers\Membership;

use App\Account\Fees;
use App\Enums\Account\ChargeKind;
use App\Enums\Membership\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Membership\ApplicationSteps;
use App\Membership\GradeOffer;
use App\Membership\Journey;
use App\Models\Charge;
use App\Models\MembershipApplication;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My membership": where a member starts an application, sees how it is
 * going, and later their grade and number.
 */
class MembershipController extends Controller
{
    public function show(Request $request): Response
    {
        /** @var MembershipApplication|null $application */
        $application = $request->user()->latestApplication()->with('state.region')->first();
        $membership = $request->user()->membership()->with('state.region')->first();
        $charges = $request->user()->charges()->with('payments')->whereNull('cancelled_at')->get();

        return Inertia::render('membership/show', [
            'journey' => Journey::for($request->user(), $application, $membership),
            'membership' => $membership ? [
                'number' => $membership->number,
                'grade' => $membership->grade->label(),
                'chapter' => $membership->state ? "{$membership->state->name}, {$membership->state->region->name}" : null,
                'joined_on' => $membership->joined_on->format('j F Y'),
                'active' => $membership->isActive(),
                'status' => $membership->statusLabel(),
                // Only what is due now; a subscription that falls due later is shown as upcoming.
                'owed' => (int) $charges->reject(fn (Charge $charge) => $charge->due_on?->isFuture() === true)->sum(fn (Charge $charge) => $charge->balance()),
                'next_due' => ($next = $charges->filter(fn (Charge $charge) => $charge->due_on?->isFuture() === true && $charge->balance() > 0)->sortBy('due_on')->first()) ? [
                    'amount' => $next->balance(),
                    'on' => $next->due_on->format('j M Y'),
                ] : null,
                // How they receive their induction: at the ceremony, or the pack posted (OASIS, 2026-11-27).
                'induction' => [
                    'choice' => $membership->induction_delivery,
                    'event' => $application?->induction_event?->label(),
                    'address' => $membership->delivery_address,
                    'city' => $membership->delivery_city,
                    'state_id' => $membership->delivery_state_id,
                    'phone' => $membership->delivery_phone,
                    'posted' => $membership->pack_posted_at?->format('j F Y'),
                    'delivered' => $membership->pack_delivered_at?->format('j F Y'),
                    'postage' => app(Fees::class)->postage(),
                ],
            ] : null,
            'regions' => $membership ? Region::query()->orderBy('name')->with('states:id,region_id,name')->get(['id', 'name'])
                ->map(fn (Region $region) => [
                    'name' => $region->name,
                    'states' => $region->states->map(fn ($state) => ['value' => (string) $state->id, 'label' => $state->name])->values(),
                ]) : [],
            'application' => $application ? [
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
                'editable' => $application->status->isEditable(),
                'grade' => $application->grade?->label(),
                'chapter' => $application->state
                    ? $application->state->name.', '.$application->state->region->name
                    : null,
                'submitted_at' => $application->submitted_at?->format('j F Y'),
                'updated_at' => $application->updated_at?->format('j F Y'),
                'steps' => collect(ApplicationSteps::STEPS)
                    ->except('review')
                    ->map(fn (string $label, string $key) => [
                        'key' => $key,
                        'label' => $label,
                        'complete' => $application->isComplete($key),
                    ])
                    ->values(),
                'next_step' => $application->missingSteps()[0] ?? 'review',
                'applied_grade' => $application->grade?->label(),
                'offered_grade' => $application->offered_grade?->label(),
                'offer_reasons' => $application->offer_reasons ?? [],
                'can_appeal' => $application->status === ApplicationStatus::Offered
                    && $application->offered_grade !== null && $application->offered_grade !== $application->grade,
                'needs_exam' => GradeOffer::needsExam($application),
                'exam_fee' => app(Fees::class)->exam(),
                // What the induction fee will be after the exam fee comes off (for those who pass).
                'induction_after_exam' => $application->offered_grade
                    ? max(0, $application->offered_grade->inductionFee() - (GradeOffer::needsExam($application) ? app(Fees::class)->exam() : 0))
                    : null,
                'exam_fee_paid' => $this->examFeePaid($request->user()),
                'exam_status' => $application->exam_status,
                'exam_best_percent' => $application->exam_best_percent !== null ? (float) $application->exam_best_percent : null,
                'decision_reason' => $application->decision_reason,
                'decision_improvements' => $application->decision_improvements,
                'registrar_message' => $application->registrar_message,
                'exemption_status' => $application->exemption_status,
                'appealed' => $application->appeal_reason !== null,
                'can_reapply' => $application->status === ApplicationStatus::Declined,
            ] : null,
        ]);
    }

    /**
     * Whether the exam fee is settled (paid or waived); null if none is owed.
     */
    private function examFeePaid(User $user): ?bool
    {
        $charge = $user->charges()->with('payments')->where('kind', ChargeKind::Exam->value)->whereNull('cancelled_at')->latest('id')->first();

        return $charge instanceof Charge ? $charge->isSettled() : null;
    }
}
