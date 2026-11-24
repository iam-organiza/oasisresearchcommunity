<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * After logging out, everyone lands on the home page, which is a Blade page.
 * Logging out from a React page is an Inertia request, and a plain redirect
 * would show the Blade page inside Inertia's pop-up, so it is a full page
 * visit instead.
 */
class LogoutResponse implements LogoutResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return Inertia::location(route('home'));
    }
}
