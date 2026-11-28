<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Where a logged-in visitor goes when they open a page meant for logged-out
 * people (log in, sign up, forgot password, a reset link), and why.
 */
final class SignedInRedirect
{
    public static function to(Request $request): string
    {
        if ($request->routeIs('password.reset')) {
            return self::fromResetLink($request);
        }

        if ($request->routeIs('password.request')) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => 'You are logged in, so you can change your password in Settings.',
            ]);

            return route('security.edit');
        }

        return route('home');
    }

    /**
     * A working reset link proves the inbox is theirs, so the visitor is
     * logged out and the link opens as it would for anyone. A dead one
     * leaves them logged in and on the password settings, with the reason.
     */
    private static function fromResetLink(Request $request): string
    {
        $token = (string) $request->route('token');
        $email = (string) $request->query('email', '');

        if (! ResetLink::isValid($token, $email)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'That reset link has expired or has already been used. You are still logged in, so you can change your password in Settings.',
            ]);

            return route('security.edit');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash('status', 'We logged you out so you can choose a new password with this link.');

        return $request->fullUrl();
    }
}
