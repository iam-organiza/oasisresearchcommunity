<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Membership\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\MembershipApplication;
use App\Models\Region;
use Inertia\Inertia;
use Inertia\Response;

class ChapterController extends Controller
{
    /**
     * The six regional chapters with the state chapters in each, and how
     * many members and applicants each has. Applicants are people with an
     * application in progress (not a draft, not yet approved or declined),
     * so nobody is counted as both.
     */
    public function __invoke(): Response
    {
        $members = Membership::query()->whereNotNull('state_id')
            ->selectRaw('state_id, count(*) as total')->groupBy('state_id')->pluck('total', 'state_id');
        $applicants = MembershipApplication::query()->whereNotNull('state_id')
            ->whereIn('status', [
                ApplicationStatus::Submitted->value,
                ApplicationStatus::Offered->value,
                ApplicationStatus::Accepted->value,
                ApplicationStatus::MoreInformation->value,
            ])
            ->selectRaw('state_id, count(*) as total')->groupBy('state_id')->pluck('total', 'state_id');

        return Inertia::render('admin/chapters/index', [
            'regions' => Region::query()
                ->orderBy('name')
                ->with('states:id,region_id,name')
                ->get(['id', 'name'])
                ->map(function (Region $region) use ($members, $applicants) {
                    $states = $region->states
                        ->map(fn ($state) => [
                            'id' => $state->id,
                            'name' => $state->name,
                            'members' => (int) ($members[$state->id] ?? 0),
                            'applicants' => (int) ($applicants[$state->id] ?? 0),
                        ])
                        ->sortBy([['members', 'desc'], ['applicants', 'desc'], ['name', 'asc']])
                        ->values();

                    return [
                        'id' => $region->id,
                        'name' => $region->name,
                        'members' => $states->sum('members'),
                        'applicants' => $states->sum('applicants'),
                        'states' => $states,
                    ];
                }),
        ]);
    }
}
