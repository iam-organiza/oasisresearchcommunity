<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Forgot password" answers the same whether or not the email has an
 * account (or was asked for a moment ago), so the page cannot be used to
 * find out who is a member.
 */
class PasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function __construct(public string $status = '') {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $message = trans('passwords.sent');

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message], 200)
            : back()->with('sentTo', (string) $request->input('email'));
    }
}
