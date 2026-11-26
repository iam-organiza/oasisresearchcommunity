<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaff
{
    /**
     * Let only staff (admins, editors and the registrar) into the staff area;
     * each area then checks its own permission.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) $request->user()?->role->isStaff(), 403);

        return $next($request);
    }
}
