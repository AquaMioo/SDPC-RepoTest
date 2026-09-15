<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;

/**
 * "Workflow" became "Project Management".
 *
 * The screen moved to ProjectManagementController, which serves both sides of
 * a signed agreement; the applications list this page used to carry is a
 * section of it now. The URL stays so bookmarks and old links still land.
 */
class WorkflowController extends Controller
{
    /**
     * Send the student to Project Management.
     */
    public function __invoke(Team $currentTeam): RedirectResponse
    {
        return redirect()->route('project-management', ['current_team' => $currentTeam]);
    }
}
