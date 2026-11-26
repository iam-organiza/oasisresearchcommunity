<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Models\MembershipApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The page a referee reaches from their email. Both routes are signed, so
 * only the person holding the link can answer, and it needs no account.
 */
class RefereeController extends Controller
{
    public function show(MembershipApplication $application): View
    {
        abort_unless($application->referee_email !== null, 404);

        return view('site.referee', [
            'application' => $application->load('user'),
            'answerUrl' => URL::temporarySignedRoute('referee.answer', now()->addHour(), ['application' => $application->id]),
        ]);
    }

    public function answer(Request $request, MembershipApplication $application): RedirectResponse
    {
        abort_unless($application->referee_email !== null, 404);

        $data = $request->validate(['answer' => ['required', Rule::in(['confirmed', 'declined'])]]);

        $application->forceFill([
            'referee_status' => $data['answer'],
            'referee_responded_at' => now(),
        ])->save();
        $application->log("referee_{$data['answer']}", null);

        return redirect()->to(URL::temporarySignedRoute('referee.show', now()->addHour(), ['application' => $application->id]));
    }
}
