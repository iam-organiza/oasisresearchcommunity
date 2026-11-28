<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Notifications\Auth\PasswordChanged;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

use function Illuminate\Support\defer;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/security', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->update([
            'password' => $request->password,
        ]);

        // Word to the inbox too, in case it was not them at the keyboard.
        $changedAt = CarbonImmutable::now();
        defer(fn () => $user->notify(new PasswordChanged(PasswordChanged::VIA_SETTINGS, $changedAt)));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
