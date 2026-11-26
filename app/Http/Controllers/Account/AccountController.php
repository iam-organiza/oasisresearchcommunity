<?php

namespace App\Http\Controllers\Account;

use App\Account\AccountStatement;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A member's own fees and payments.
 */
class AccountController extends Controller
{
    public function show(Request $request): Response
    {
        $membership = $request->user()->membership;

        return Inertia::render('membership/account', [
            'statement' => AccountStatement::for($request->user()),
            'member' => $membership ? [
                'number' => $membership->number,
                'active' => $membership->isActive(),
                'status' => $membership->statusLabel(),
            ] : null,
        ]);
    }
}
