<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * The open tab saying the person is still here.
 *
 * Nothing happens in the controller. The work is done on the way in: the web
 * group's EnforceSingleSession answers 409 to a device that no longer holds the
 * account, and TouchLastSeen stamps the account for the one that does — which
 * is what keeps it counting as in use while somebody reads a page or sits in a
 * call without clicking anything.
 */
class SessionHeartbeatController extends Controller
{
    public function __invoke(): Response
    {
        return response()->noContent();
    }
}
