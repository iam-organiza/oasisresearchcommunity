<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Membership\ApplicationStatus;
use App\Enums\Membership\Grade;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\MembershipApplication;
use App\Models\Region;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The registrar's overview: what is waiting, the register by grade, and
 * where applicants and members are, by region and state, to help decide
 * where to hold inductions.
 */
class RegistrarController extends Controller
{
    public function __invoke(): Response
    {
        $statusCounts = MembershipApplication::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byGrade = Membership::query()
            ->selectRaw('grade, count(*) as total, sum(case when activated_at is not null then 1 else 0 end) as active')
            ->groupBy('grade')
            ->get()
            ->keyBy('grade');

        // Applicants (submitted or further along) and members, counted by state.
        $applicantsByState = MembershipApplication::query()
            ->where('status', '!=', ApplicationStatus::Draft->value)
            ->whereNotNull('state_id')
            ->selectRaw('state_id, count(*) as total')
            ->groupBy('state_id')
            ->pluck('total', 'state_id');
        $membersByState = Membership::query()
            ->whereNotNull('state_id')
            ->selectRaw('state_id, count(*) as total')
            ->groupBy('state_id')
            ->pluck('total', 'state_id');

        $regions = Region::query()->orderBy('name')->with('states:id,region_id,name')->get()
            ->map(function (Region $region) use ($applicantsByState, $membersByState) {
                $states = $region->states->map(fn ($state) => [
                    'name' => $state->name,
                    'applicants' => (int) ($applicantsByState[$state->id] ?? 0),
                    'members' => (int) ($membersByState[$state->id] ?? 0),
                ])->filter(fn (array $state) => $state['applicants'] + $state['members'] > 0)
                    ->sortByDesc(fn (array $state) => $state['applicants'] + $state['members'])
                    ->values();

                return [
                    'name' => $region->name,
                    'applicants' => $states->sum('applicants'),
                    'members' => $states->sum('members'),
                    'states' => $states,
                ];
            })
            ->sortByDesc(fn (array $region) => $region['applicants'] + $region['members'])
            ->values();

        return Inertia::render('admin/registrar/index', [
            'waiting' => (int) ($statusCounts[ApplicationStatus::Submitted->value] ?? 0),
            'statuses' => collect([
                ApplicationStatus::Draft, ApplicationStatus::Submitted, ApplicationStatus::MoreInformation,
                ApplicationStatus::Recommended, ApplicationStatus::Approved, ApplicationStatus::Declined,
            ])->map(fn (ApplicationStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'total' => (int) ($statusCounts[$status->value] ?? 0),
            ]),
            'grades' => collect(Grade::cases())->map(fn (Grade $grade) => [
                'label' => $grade->label(),
                'total' => (int) ($byGrade[$grade->value]->total ?? 0),
                'active' => (int) ($byGrade[$grade->value]->active ?? 0),
            ]),
            'regions' => $regions,
            'members' => Membership::query()->count(),
        ]);
    }
}
