<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\ConfineDeactivatedAccounts;
use App\Http\Middleware\EndSessionOnLoginScreen;
use App\Http\Middleware\EnforceSingleSession;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTeamUrlDefaults;
use App\Http\Middleware\TouchLastSeen;
use App\Support\AuthHome;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Which proxies may tell the app the visitor's real address and
         * scheme is config('trustedproxy.proxies') — TRUSTED_PROXIES in .env —
         * and deliberately not an `at:` here, which would override it on
         * every host. See config/trustedproxy.php.
         */

        $middleware->encryptCookies(except: ['sidebar_state']);

        $middleware->web(append: [
            // First: nothing after it may act for a device that does not hold the account.
            EnforceSingleSession::class,
            EndSessionOnLoginScreen::class,
            // A deactivated account signs in to Settings and its appeal, and nowhere else.
            ConfineDeactivatedAccounts::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetTeamUrlDefaults::class,
            AddSecurityHeaders::class,
            TouchLastSeen::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        $middleware->redirectTo(
            // Guests are sent to the login screen of the portal they asked for.
            guests: fn (Request $request): string => $request->routeIs('admin.*')
                ? route('admin.login')
                : route('login'),
            // Authenticated users hitting a guest-only page go to their own home.
            users: fn (Request $request): string => AuthHome::for($request->user()),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
