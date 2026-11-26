<?php

namespace App\Http\Controllers\Membership;

use App\Enums\Membership\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Membership\ApplicationSteps;
use App\Models\MembershipApplication;
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

        return Inertia::render('membership/show', [
            'membership' => $membership ? [
                'number' => $membership->number,
                'grade' => $membership->grade->label(),
                'chapter' => $membership->state ? "{$membership->state->name}, {$membership->state->region->name}" : null,
                'joined_on' => $membership->joined_on->format('j F Y'),
                'active' => $membership->isActive(),
                'status' => $membership->statusLabel(),
                'fee_waived' => $membership->fee_waived,
                'induction_fee' => $membership->grade->inductionFee(),
            ] : null,
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
                'recommended_grade' => $application->decided_grade?->label(),
                'decision_reason' => $application->decision_reason,
                'decision_improvements' => $application->decision_improvements,
                'registrar_message' => $application->registrar_message,
                'exemption_status' => $application->exemption_status,
                'appealed' => $application->appeal_reason !== null,
                'can_reapply' => $application->status === ApplicationStatus::Declined,
            ] : null,
        ]);
    }
}
