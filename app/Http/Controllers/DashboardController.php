<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\TeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|Response
    {
        $user = $request->user();

        /**
         * Clients have a workspace of their own, so this generic dashboard is
         * only ever a dead end for them. Bookmarks and the sidebar link both
         * land here, which is why the hand-off lives in the controller rather
         * than in the post-login redirect alone.
         */
        if ($user->hasRole(UserRole::Client)) {
            return redirect()->route('client.dashboard', [
                'current_team' => $user->currentTeam,
            ]);
        }

        return Inertia::render('dashboard', [
            'pendingInvitations' => TeamInvitation::pendingForDashboard($user->email),
        ]);
    }
}
