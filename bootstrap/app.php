<?php

use App\Http\Middleware\AddSecurityHeaders;
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
         * Behind a load balancer that terminates TLS — Railway, Fly, Render,
         * anything with a managed certificate — the app itself is spoken to
         * over plain HTTP. Without trusting the proxy's X-Forwarded-* headers
         * Laravel believes the request is insecure, and then: route() and
         * asset() emit http:// links that a browser on an https:// page
         * refuses to load, the session cookie loses its Secure flag, and the
         * HSTS header in AddSecurityHeaders never fires because
         * $request->secure() is false.
         *
         * `at: '*'` trusts whatever is in front. That is only safe because a
         * managed platform is the sole route in — never do this on a host
         * where the app port is reachable directly, or a client can forge
         * X-Forwarded-For and spoof its own address.
         */
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['sidebar_state']);

        $middleware->web(append: [
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
