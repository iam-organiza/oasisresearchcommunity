<?php

namespace App\Http\Controllers\Account;

use App\Account\OnlineCheckout;
use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\OnlinePayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * A member paying one of their charges online, and coming back from Paystack.
 */
class OnlinePaymentController extends Controller
{
    public function start(Request $request, Charge $charge, OnlineCheckout $checkout): Response
    {
        abort_unless($charge->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', Rule::in(['NGN', 'USD'])],
        ], ['amount.required' => 'Enter how much to pay, in naira.']);

        $url = $checkout->start($charge, (int) $data['amount'], $data['currency'], route('membership.payments.return'));

        return Inertia::location($url);
    }

    /**
     * Paystack sends the member back here with the reference.
     */
    public function return(Request $request, OnlineCheckout $checkout): RedirectResponse
    {
        $attempt = OnlinePayment::query()
            ->where('reference', (string) $request->query('reference', $request->query('trxref', '')))
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $attempt) {
            return to_route('membership.account');
        }

        $attempt = $checkout->confirm($attempt);

        // Shown as a notice on the page: the member arrives back from Paystack on a full page load.
        $notice = match ($attempt->status) {
            'paid' => ['tone' => 'success', 'message' => "Payment received: {$attempt->money($attempt->total_minor)}. Your receipt is on its way by email."],
            'failed' => ['tone' => 'error', 'message' => 'The payment did not go through, and nothing was recorded. You can try again.'],
            'needs_attention' => ['tone' => 'error', 'message' => 'Paystack took your payment but we could not apply it to this charge. The Registrar has been told and will sort it out; you do not need to pay again.'],
            default => ['tone' => 'info', 'message' => 'We are waiting for Paystack to confirm your payment. It will show here within a few minutes. Please do not pay again.'],
        };

        return to_route('membership.account')->with('payment_notice', $notice);
    }
}
