<?php

namespace App\Http\Controllers\Account;

use App\Account\AccountStatement;
use App\Account\ExchangeRate;
use App\Account\OnlineCheckout;
use App\Account\Paystack;
use App\Http\Controllers\Controller;
use App\Models\OnlinePayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A member's own fees and payments, and paying them online.
 */
class AccountController extends Controller
{
    public function show(Request $request, Paystack $paystack, ExchangeRate $rates): Response
    {
        $user = $request->user();
        $membership = $user->membership;

        return Inertia::render('membership/account', [
            'statement' => AccountStatement::for($user),
            'member' => $membership ? [
                'number' => $membership->number,
                'active' => $membership->isActive(),
                'status' => $membership->statusLabel(),
            ] : null,
            'notice' => $request->session()->get('payment_notice'),
            'online' => $paystack->enabled() ? [
                'usd' => $paystack->usdEnabled() && $rates->current() !== null,
                // The dialog works out Paystack's charge with the same figures the server uses.
                'fees' => config('services.paystack.fees'),
                'rate' => $rates->current(),
                'margin' => (int) config('membership.dollar_margin_percent'),
                'minimum_part' => OnlineCheckout::MINIMUM_PART,
            ] : null,
            // Payments started in the last day that Paystack has not confirmed yet, so nobody pays twice.
            'waiting' => OnlinePayment::query()
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->where('created_at', '>=', now()->subDay())
                ->with('charge')
                ->latest()
                ->get()
                ->map(fn (OnlinePayment $attempt) => [
                    'reference' => $attempt->reference,
                    'charge' => $attempt->charge->label(),
                    'total' => $attempt->money($attempt->total_minor),
                    'started' => $attempt->created_at->format('j M, g:i a'),
                ]),
        ]);
    }
}
