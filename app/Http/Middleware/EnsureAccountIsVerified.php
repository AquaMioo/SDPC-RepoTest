<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps an unproven account read only.
 *
 * Registering gets you in and lets you look around. It does not let you post
 * work, hire anyone, or apply for anything — those wait until an administrator
 * has accepted the document that proves what the account claims to be: a
 * business permit for a client, a credential for a student.
 *
 * Applied to the write routes only. Every read route stays open on purpose, so
 * someone can see what the module offers, and reach the profile screen that
 * lets them submit the document in the first place.
 */
class EnsureAccountIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, Response::HTTP_FORBIDDEN);

        if ($user->isVerifiedForOperating()) {
            return $next($request);
        }

        return back()->withErrors([
            'verification' => __('Your account is not verified yet. Complete your profile and upload your document, then an administrator will review it.'),
        ]);
    }
}
