<?php

use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\SignedInRedirect;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias(['staff' => EnsureStaff::class]);

        // Logged-in visitors on log-in, sign-up and password reset pages.
        $middleware->redirectUsersTo(fn (Request $request) => SignedInRedirect::to($request));

        $middleware->web(append: [
            // Signs a session out once its account's password changes elsewhere (a reset, say).
            AuthenticateSession::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // An expired or broken email verification link: back to "Check your email"
        // (or to log in first) with a way to get a new one, not a bare 403 page.
        $exceptions->render(function (InvalidSignatureException $exception, Request $request) {
            if (! $request->routeIs('verification.verify')) {
                return null;
            }

            return $request->user()
                ? redirect()->route('verification.notice')->with('status', 'verification-link-expired')
                : redirect()->route('login')->with('status', 'That link has expired or is incomplete. Log in and we will help you get a new one.');
        });
    })->create();
