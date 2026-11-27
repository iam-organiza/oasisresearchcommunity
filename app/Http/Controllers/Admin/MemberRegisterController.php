<?php

namespace App\Http\Controllers\Admin;

use App\Account\Account;
use App\Enums\Account\PaymentMethod;
use App\Enums\Membership\Grade;
use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Membership;
use App\Models\Region;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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
 * chapter, whether they are active and what they owe. The registrar can also record
 * people admitted before the website, without an application.
 */
class MemberRegisterController extends Controller
{
    public function index(Request $request): Response
    {
        $grade = Grade::tryFrom((string) $request->query('grade'));
        $region = $request->integer('region') ?: null;
        $fees = in_array($request->query('fees'), ['awaiting', 'active', 'lapsed'], true) ? $request->query('fees') : '';
        $search = trim((string) $request->query('search'));

        // Grade, region and search; the status chips count within these.
        $filtered = fn () => Membership::query()
            ->when($grade, fn (Builder $query) => $query->where('grade', $grade->value))
            ->when($region, fn (Builder $query) => $query->whereHas('state', fn (Builder $state) => $state->where('region_id', $region)))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('user', fn (Builder $user) => $user
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))));
        $counts = [
            'all' => $filtered()->count(),
            'active' => $filtered()->whereNotNull('activated_at')->whereNull('lapsed_at')->count(),
            'awaiting' => $filtered()->whereNull('activated_at')->count(),
            'lapsed' => $filtered()->whereNotNull('activated_at')->whereNotNull('lapsed_at')->count(),
        ];

        $members = Membership::query()
            ->with(['user:id,first_name,last_name,email', 'user.charges.payments', 'state.region'])
            ->when($grade, fn (Builder $query) => $query->where('grade', $grade->value))
            ->when($region, fn (Builder $query) => $query->whereHas('state', fn (Builder $state) => $state->where('region_id', $region)))
            ->when($fees === 'awaiting', fn (Builder $query) => $query->whereNull('activated_at'))
            ->when($fees === 'active', fn (Builder $query) => $query->whereNotNull('activated_at')->whereNull('lapsed_at'))
            ->when($fees === 'lapsed', fn (Builder $query) => $query->whereNotNull('activated_at')->whereNotNull('lapsed_at'))
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
                // Only what is due now; a subscription added ahead of its due date is shown as next.
                'due_now' => (int) $membership->user->charges->reject(fn (Charge $charge) => $charge->due_on?->isFuture() === true)->sum(fn (Charge $charge) => $charge->balance()),
                'next_due' => ($next = $membership->user->charges->filter(fn (Charge $charge) => $charge->due_on?->isFuture() === true && $charge->balance() > 0)->sortBy('due_on')->first())
                    ? ['amount' => $next->balance(), 'on' => $next->due_on->format('j M Y')] : null,
                'user_id' => $membership->user_id,
                'application_id' => $membership->membership_application_id,
            ]);

        return Inertia::render('admin/members/index', [
            'members' => $members,
            'counts' => $counts,
            'filters' => ['grade' => $grade->value ?? '', 'region' => $region ? (string) $region : '', 'fees' => $fees, 'search' => $search],
            'grades' => $this->gradeOptions(),
            'regions' => Region::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Region $region) => ['value' => (string) $region->id, 'label' => $region->name]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/members/create', [
            'grades' => collect(Grade::cases())->map(fn (Grade $grade) => ['value' => $grade->value, 'label' => $grade->label(), 'induction_fee' => $grade->inductionFee()]),
            'regions' => Region::query()->orderBy('name')->with('states:id,region_id,name')->get(['id', 'name'])
                ->map(fn (Region $region) => [
                    'name' => $region->name,
                    'states' => $region->states->map(fn ($state) => ['value' => (string) $state->id, 'label' => $state->name])->values(),
                ]),
        ]);
    }

    /**
     * For the form, as the Registrar types: whose account an email belongs
     * to, whether they are already on the register, and the number the
     * register would give them for a grade and year.
     */
    public function lookup(Request $request): JsonResponse
    {
        $email = Str::lower(trim((string) $request->query('email')));
        $user = $email !== '' ? User::query()->where('email', $email)->with('membership')->first() : null;
        $grade = Grade::tryFrom((string) $request->query('grade'));
        $year = $request->integer('year') ?: now()->year;

        return response()->json([
            'account' => $user ? [
                'name' => $user->name,
                'on_register' => $user->membership !== null,
                'number' => $user->membership?->number,
            ] : null,
            'next_number' => $grade && $year >= 1990 && $year <= now()->year ? Membership::nextNumber($grade, $year) : null,
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
            'induction' => ['required', Rule::in(['owed', 'paid', 'waived'])],
            'induction_amount' => ['required', 'integer', 'min:1', 'max:100000000'],
        ], [
            'induction.required' => 'Say whether their induction fee is still owed, already paid or waived.',
            'induction_amount.required' => 'Enter their induction fee in naira.',
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

            $membership = Membership::query()->create([
                'user_id' => $user->id,
                'number' => ($data['number'] ?? null) ?: Membership::nextNumber($grade, $joined->year),
                'grade' => $grade,
                'state_id' => $data['state_id'] ?? null,
                'joined_on' => $joined->toDateString(),
                'recorded_by' => $request->user()->id,
            ]);

            $charge = Account::addInductionFee($membership, $request->user(), waived: $data['induction'] === 'waived', waiverReason: 'Waived before they joined the website.', amount: (int) $data['induction_amount']);
            if ($data['induction'] === 'paid') {
                Account::recordPayment($charge, $charge->amount, PaymentMethod::BeforeWebsite, $joined, $request->user());
            }

            return $membership;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} added to the register as {$membership->number}."]);

        return to_route('admin.members.index');
    }

    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    private function gradeOptions(): Collection
    {
        return collect(Grade::cases())->map(fn (Grade $grade) => ['value' => $grade->value, 'label' => $grade->label()]);
    }
}
