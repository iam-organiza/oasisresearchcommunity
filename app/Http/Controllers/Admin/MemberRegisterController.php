<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Membership\Grade;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Region;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The member register: everyone with a membership number, their grade,
 * chapter and whether the induction fee is in. The registrar can also record
 * people admitted before the website, without an application.
 */
class MemberRegisterController extends Controller
{
    public function index(Request $request): Response
    {
        $grade = Grade::tryFrom((string) $request->query('grade'));
        $region = $request->integer('region') ?: null;
        $fees = in_array($request->query('fees'), ['awaiting', 'active'], true) ? $request->query('fees') : '';
        $search = trim((string) $request->query('search'));

        $members = Membership::query()
            ->with(['user:id,first_name,last_name,email', 'state.region'])
            ->when($grade, fn (Builder $query) => $query->where('grade', $grade->value))
            ->when($region, fn (Builder $query) => $query->whereHas('state', fn (Builder $state) => $state->where('region_id', $region)))
            ->when($fees === 'awaiting', fn (Builder $query) => $query->whereNull('induction_paid_at')->where('fee_waived', false))
            ->when($fees === 'active', fn (Builder $query) => $query->where(fn (Builder $active) => $active->whereNotNull('induction_paid_at')->orWhere('fee_waived', true)))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('user', fn (Builder $user) => $user
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))))
            ->orderByDesc('joined_on')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Membership $membership) => [
                'id' => $membership->id,
                'number' => $membership->number,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'grade' => $membership->grade->label(),
                'chapter' => $membership->state ? "{$membership->state->name}, {$membership->state->region->name}" : null,
                'joined_on' => $membership->joined_on->format('j M Y'),
                'active' => $membership->isActive(),
                'status' => $membership->statusLabel(),
                'fee_waived' => $membership->fee_waived,
                'application_id' => $membership->membership_application_id,
            ]);

        return Inertia::render('admin/members/index', [
            'members' => $members,
            'filters' => ['grade' => $grade->value ?? '', 'region' => $region ? (string) $region : '', 'fees' => $fees, 'search' => $search],
            'grades' => $this->gradeOptions(),
            'regions' => Region::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Region $region) => ['value' => (string) $region->id, 'label' => $region->name]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/members/create', [
            'grades' => $this->gradeOptions(),
            'regions' => Region::query()->orderBy('name')->with('states:id,region_id,name')->get(['id', 'name'])
                ->map(fn (Region $region) => [
                    'name' => $region->name,
                    'states' => $region->states->map(fn ($state) => ['value' => (string) $state->id, 'label' => $state->name])->values(),
                ]),
        ]);
    }

    /**
     * Record someone admitted before the website. They must already have an account.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'grade' => ['required', Rule::enum(Grade::class)],
            'state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'number' => ['nullable', 'string', 'max:40', 'regex:/^OASIS\/(FEL|MEM|GRD|STU)\/\d{4}\/\d{4}$/', Rule::unique('memberships', 'number')],
            'joined_on' => ['required', 'date', 'before_or_equal:today'],
            'induction_paid' => ['boolean'],
            'fee_waived' => ['boolean'],
        ], [
            'email.required' => 'Enter the email address of their account.',
            'number.regex' => 'Use the format OASIS/FEL/2026/0012, or leave it empty to create the next number.',
            'number.unique' => 'Another member already has this number.',
        ]);

        $user = User::query()->where('email', Str::lower($data['email']))->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => 'No account uses this email address. Ask them to create an account first.']);
        }

        if ($user->membership()->exists()) {
            throw ValidationException::withMessages(['email' => "{$user->name} is already on the register."]);
        }

        $membership = DB::transaction(function () use ($data, $user, $request): Membership {
            $grade = Grade::from($data['grade']);
            $joined = CarbonImmutable::parse($data['joined_on']);

            return Membership::query()->create([
                'user_id' => $user->id,
                'number' => ($data['number'] ?? null) ?: Membership::nextNumber($grade, $joined->year),
                'grade' => $grade,
                'state_id' => $data['state_id'] ?? null,
                'joined_on' => $joined->toDateString(),
                'induction_paid_at' => ($data['induction_paid'] ?? false) ? now() : null,
                'fee_waived' => $data['fee_waived'] ?? false,
                'recorded_by' => $request->user()->id,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} added to the register as {$membership->number}."]);

        return to_route('admin.members.index');
    }

    /**
     * Mark the induction fee as received (or not), or waive it.
     */
    public function updateFees(Request $request, Membership $membership): RedirectResponse
    {
        $data = $request->validate([
            'induction_paid' => ['sometimes', 'boolean'],
            'fee_waived' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('induction_paid', $data)) {
            $membership->induction_paid_at = $data['induction_paid'] ? ($membership->induction_paid_at ?? now()) : null;
        }
        if (array_key_exists('fee_waived', $data)) {
            $membership->fee_waived = $data['fee_waived'];
        }
        $membership->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$membership->number}: {$membership->statusLabel()}."]);

        return back();
    }

    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    private function gradeOptions(): Collection
    {
        return collect(Grade::cases())->map(fn (Grade $grade) => ['value' => $grade->value, 'label' => $grade->label()]);
    }
}
