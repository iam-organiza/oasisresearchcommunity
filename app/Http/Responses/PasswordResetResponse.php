<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * After a new password: to log in, with the message at the top and the email
 * already filled in.
 */
class PasswordResetResponse implements PasswordResetResponseContract
{
    public function __construct(public string $status = '') {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse(['message' => trans($this->status)], 200)
            : redirect()->route('login')
                ->with('status', trans($this->status))
                ->with('email', (string) $request->input('email'));
    }
}
