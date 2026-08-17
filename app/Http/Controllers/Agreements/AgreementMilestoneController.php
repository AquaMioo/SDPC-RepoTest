<?php

namespace App\Http\Controllers\Agreements;

use App\Actions\Billing\RecordTransaction;
use App\Enums\MilestoneStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agreements\UpdateMilestoneRequest;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;

/**
 * Project Progress Tracking, for both sides.
 *
 * The student says what they have started and handed over; the client says
 * what they accept and what they are sending back. Between them they produce
 * the only progress figure the platform reports, which is why neither side can
 * move a milestone the whole way on their own.
 */
class AgreementMilestoneController extends Controller
{
    /**
     * Move a milestone into the status the acting party chose.
     *
     * Both route models are declared, in the order the URL carries them.
     * Leaving `$agreement` out does not make it optional — Laravel fills the
     * parameters positionally, so the agreement id lands in `$milestone` and
     * the request dies on a type error.
     */
    public function update(
        UpdateMilestoneRequest $request,
        Team $currentTeam,
        Agreement $agreement,
        AgreementMilestone $milestone,
        RecordTransaction $recordTransaction,
    ): RedirectResponse {
        /*
         * The URL says which contract this is. A milestone id belonging to a
         * different one must not resolve just because the id exists — without
         * this, the only thing standing between the two is that nobody tried.
         */
        abort_unless($milestone->agreement_id === $agreement->id, 404);

        $status = $request->status();

        $milestone->update([
            'status' => $status,
            'review_note' => $request->input('review_note'),
            'submitted_at' => $status === MilestoneStatus::Submitted ? now() : $milestone->submitted_at,
            'approved_at' => $status === MilestoneStatus::Approved ? now() : null,
            'approved_by' => $status === MilestoneStatus::Approved ? $request->user()->id : null,
        ]);

        /*
         * Approval is what makes a milestone payable, so the ledger row is
         * written here. It is a no-op while billing is switched off, which is
         * why this call is unconditional — the decision lives in one place.
         */
        if ($status === MilestoneStatus::Approved) {
            $recordTransaction->forMilestone($milestone->refresh());
        }

        return back()->with('success', 'Milestone updated.');
    }
}
