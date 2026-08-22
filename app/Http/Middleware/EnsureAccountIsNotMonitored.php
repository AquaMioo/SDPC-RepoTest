<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds a monitored account back from acting.
 *
 * An administrator sets UserStatus::Monitored when an account is under review
 * but not yet decided against. That is a hold, not a ban: the account keeps
 * signing in, keeps reading, keeps talking to the people it is already working
 * with — and above all keeps the settings screen where its appeal is written.
 * What stops is posting work, applying, hiring, signing and speaking publicly.
 *
 * Applied beside EnsureAccountIsVerified on exactly the same routes, because
 * they answer the same question from two directions: one asks whether the
 * account has proved itself yet, this one whether it is still trusted.
 */
class EnsureAccountIsNotMonitored
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

        if (! $user->status->restrictsActions()) {
            return $next($request);
        }

        return back()->withErrors([
            'monitoring' => __('Your account is under review, so this action is on hold. You can appeal from your account settings.'),
        ]);
    }
}
