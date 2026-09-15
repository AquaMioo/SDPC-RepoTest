<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;

/**
 * "Project process" became "Project Management".
 *
 * Milestone tracking grew into Granular Task Completion — a checklist per
 * phase that the client verifies — and moved to ProjectManagementController,
 * shared by both sides. The URL stays so the old links still land.
 */
class ProjectProcessController extends Controller
{
    /**
     * Send the student to Project Management.
     */
    public function __invoke(Team $currentTeam): RedirectResponse
    {
        return redirect()->route('project-management', ['current_team' => $currentTeam]);
    }
}
