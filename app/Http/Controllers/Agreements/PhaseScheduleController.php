<?php

namespace App\Http\Controllers\Agreements;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agreements\UpdatePhaseScheduleRequest;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The timeline on Project Management: the student moving a phase's dates.
 *
 * Writes the planned dates only. starts_on and ends_on are what both sides
 * signed, and signed terms move through a change request, never through a
 * drag — so the agreed dates stay on the screen as the baseline the plan is
 * measured against.
 */
class PhaseScheduleController extends Controller
{
    /**
     * Save a phase's planned start and end.
     */
    public function update(
        UpdatePhaseScheduleRequest $request,
        Team $currentTeam,
        Agreement $agreement,
        AgreementMilestone $milestone,
    ): RedirectResponse {
        abort_unless($milestone->agreement_id === $agreement->id, Response::HTTP_NOT_FOUND);

        $milestone->update([
            'planned_starts_on' => $request->validated('starts_on'),
            'planned_ends_on' => $request->validated('ends_on'),
        ]);

        return back();
    }
}
