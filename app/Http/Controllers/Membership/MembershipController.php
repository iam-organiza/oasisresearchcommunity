<?php

namespace App\Http\Controllers\Membership;

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

        return Inertia::render('membership/show', [
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
            ] : null,
        ]);
    }
}
