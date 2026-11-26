<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * A printable receipt, for the payer and for staff who manage members.
 */
class ReceiptController extends Controller
{
    public function show(Request $request, Payment $payment): View
    {
        abort_unless($payment->user_id === $request->user()->id || Gate::allows('manage-members'), 404);
        abort_if($payment->isVoided(), 404);

        $payment->load(['charge.payments', 'user.membership']);

        return view('account.receipt', ['payment' => $payment]);
    }
}
