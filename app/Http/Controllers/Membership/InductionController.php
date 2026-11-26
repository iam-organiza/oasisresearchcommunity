<?php

namespace App\Http\Controllers\Membership;

use App\Account\Account;
use App\Account\Fees;
use App\Enums\Account\ChargeKind;
use App\Http\Controllers\Controller;
use App\Models\Charge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * How a new member receives their induction (OASIS, 2026-11-27): at the
 * ceremony, or with the induction pack (certificates, award, gown) posted
 * to them. The delivery address is asked only for posting and deleted once
 * the pack is delivered. Postage, if OASIS sets it, goes on their account.
 */
class InductionController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $membership = $request->user()->membership;
        abort_unless($membership !== null, 404);

        if ($membership->pack_posted_at !== null) {
            throw ValidationException::withMessages(['choice' => 'Your pack is already on its way. Contact the Secretariat to change anything.']);
        }

        $data = $request->validate([
            'choice' => ['required', Rule::in(['ceremony', 'post'])],
            'address' => ['nullable', 'required_if:choice,post', 'string', 'max:300'],
            'city' => ['nullable', 'required_if:choice,post', 'string', 'max:100'],
            'state_id' => ['nullable', 'required_if:choice,post', 'integer', Rule::exists('states', 'id')],
            'phone' => ['nullable', 'required_if:choice,post', 'string', 'max:20', 'regex:/^[0-9 +()-]{7,20}$/'],
        ], [
            'choice.required' => 'Choose how you will receive your induction.',
            'address.required_if' => 'Enter the street address for the courier.',
            'city.required_if' => 'Enter the city or town.',
            'state_id.required_if' => 'Choose the state.',
            'phone.required_if' => 'Enter a phone number the courier can call.',
            'phone.regex' => 'Enter a phone number using digits, spaces, + or brackets.',
        ]);

        $post = $data['choice'] === 'post';

        DB::transaction(function () use ($membership, $data, $post, $request) {
            $membership->forceFill([
                'induction_delivery' => $data['choice'],
                'delivery_address' => $post ? $data['address'] : null,
                'delivery_city' => $post ? $data['city'] : null,
                'delivery_state_id' => $post ? $data['state_id'] : null,
                'delivery_phone' => $post ? $data['phone'] : null,
            ])->save();

            $postage = Charge::query()->with('payments')->where('user_id', $membership->user_id)
                ->where('kind', ChargeKind::Postage->value)->whereNull('cancelled_at')->first();
            $fee = app(Fees::class)->postage();

            if ($post && $postage === null && $fee > 0) {
                Account::addCharge($request->user(), ChargeKind::Postage, $fee, null);
            }
            if (! $post && $postage !== null && $postage->paidAmount() === 0) {
                Account::cancel($postage, 'Changed to attending the ceremony.', null);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => $post
            ? 'Saved. We will post your induction pack to this address.'
            : 'Saved. We look forward to seeing you at the ceremony.']);

        return back();
    }
}
