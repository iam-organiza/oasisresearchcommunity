<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Stopping (or restarting) the monthly update, from the signed link in it.
 * No sign-in needed. The page asks first, so a mail scanner opening the
 * link does not unsubscribe anyone.
 */
class NewsletterController extends Controller
{
    public function show(User $user): View
    {
        return view('account.newsletter', [
            'user' => $user,
            'action' => URL::signedRoute('newsletter.update', ['user' => $user->id]),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $stop = $request->boolean('stop');
        $user->forceFill(['newsletter_opted_out_at' => $stop ? now() : null])->save();

        return redirect()->to(URL::signedRoute('newsletter.unsubscribe', ['user' => $user->id]));
    }
}
