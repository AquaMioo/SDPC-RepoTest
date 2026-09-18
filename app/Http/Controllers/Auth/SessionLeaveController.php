<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccountSession;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The last open tab saying the person has gone.
 *
 * The heartbeat's other half. EnforceSingleSession has already turned away a
 * device that no longer holds the account, and AccountSession::leave() checks
 * again, so only the holder can free it. The browser stays signed in: this
 * only stops the account counting as in use until it comes back.
 */
class SessionLeaveController extends Controller
{
    public function __construct(private readonly AccountSession $accountSession) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->accountSession->leave($request, $user);
        }

        return response()->noContent();
    }
}
