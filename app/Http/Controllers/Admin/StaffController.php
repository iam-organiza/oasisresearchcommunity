<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admins choose who holds a staff role. A role is given to someone who
 * already has an account on the site, and taking it away makes them a
 * member again; nobody is deleted here.
 */
class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/staff/index', [
            'staff' => User::query()
                ->where('role', '!=', UserRole::Member->value)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'is_you' => $user->is($request->user()),
                ]),
            'roles' => collect(UserRole::cases())
                ->reject(fn (UserRole $role) => $role === UserRole::Member)
                ->map(fn (UserRole $role) => [
                    'value' => $role->value,
                    'label' => $role->label(),
                    'description' => $role->description(),
                ])
                ->values(),
        ]);
    }

    /**
     * Give a staff role to an existing account, found by its email address.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::enum(UserRole::class)->except([UserRole::Member])],
        ], [
            'email.required' => 'Enter the email address of their account.',
        ]);

        $user = User::query()->where('email', Str::lower($data['email']))->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'No account uses this email address. Ask them to create an account first.',
            ]);
        }

        if ($user->role->isStaff()) {
            throw ValidationException::withMessages([
                'email' => "{$user->name} is already on the staff list; change their role there.",
            ]);
        }

        $user->forceFill(['role' => UserRole::from($data['role'])])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} is now {$this->article($user->role)}."]);

        return back();
    }

    /**
     * Change a staff member's role, or make them a member again.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $role = UserRole::from($data['role']);

        // An admin could otherwise lock themselves out of this page.
        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'role' => 'You cannot change your own role. Ask another admin.',
            ]);
        }

        if ($user->role === UserRole::Admin && $role !== UserRole::Admin
            && User::query()->where('role', UserRole::Admin->value)->count() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'The site needs at least one admin.',
            ]);
        }

        $user->forceFill(['role' => $role])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => $role === UserRole::Member
            ? "{$user->name} is no longer on the staff."
            : "{$user->name} is now {$this->article($role)}."]);

        return back();
    }

    private function article(UserRole $role): string
    {
        return match ($role) {
            UserRole::Admin => 'an admin',
            UserRole::Editor => 'an editor',
            UserRole::Registrar => 'a registrar',
            UserRole::Member => 'a member',
        };
    }
}
