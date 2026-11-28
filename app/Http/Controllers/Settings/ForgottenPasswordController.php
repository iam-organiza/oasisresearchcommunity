<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Logged in but the password is forgotten, so settings cannot be opened:
 * log out and go straight to "Forgot your password?" with the email filled in.
 */
class ForgottenPasswordController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $email = (string) $request->user()?->email;

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('password.request', ['email' => $email]);
    }
}
