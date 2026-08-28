<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signing out, plus throwing away the browser's copy of everything seen.
 *
 * Inertia keeps each visited page's props in the window's history state so
 * that Back is instant, and logging out did not touch it. From the login
 * screen you were returned to, pressing Forward drew the admin dashboard again
 * — user counts, account list, review queue — straight out of history, with no
 * request to the server at all. The session had gone; the browser never asked.
 *
 * clearHistory() rotates the key that state is encrypted with, so every
 * earlier entry stops being decryptable and Inertia has to fetch the page —
 * landing on the login screen, which is the point.
 *
 * It has to happen HERE and not on the Logout event. clearHistory() leaves a
 * flag in the session for the next Inertia response to pick up, and the event
 * fires before Fortify calls session()->invalidate() — which threw the flag
 * away again. This runs afterwards.
 *
 * Fortify's logout route serves both portals, so the admin side is covered by
 * the same class. Deleting your own account is a separate path and clears it
 * itself; see ProfileController::destroy.
 */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): Response
    {
        Inertia::clearHistory();

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect(Fortify::redirects('logout', '/'));
    }
}
