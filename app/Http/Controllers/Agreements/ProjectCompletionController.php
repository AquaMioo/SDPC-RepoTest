<?php

namespace App\Http\Controllers\Agreements;

use App\Actions\Agreements\CompleteProject;
use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * The client's Complete button, at the foot of the Turnover phase.
 *
 * Only the client may press it (AgreementPolicy::complete), and only on a
 * build still in flight. It is allowed with tasks still unverified — the
 * screen asks the client to confirm and says how many — because the turnover
 * is the client's to accept, not a percentage's.
 */
class ProjectCompletionController extends Controller
{
    /**
     * Complete the agreement's project.
     */
    public function store(Team $currentTeam, Agreement $agreement, CompleteProject $completeProject): RedirectResponse
    {
        Gate::authorize('complete', $agreement);

        $completeProject->handle($agreement);

        return to_route('project-management', [
            'current_team' => $currentTeam,
            'agreement' => $agreement->id,
        ])->with('success', __('Project completed. Everyone on it is free to take on new work.'));
    }
}
