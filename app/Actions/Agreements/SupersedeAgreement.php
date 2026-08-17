<?php

namespace App\Actions\Agreements;

use App\Enums\AgreementStatus;
use App\Enums\MilestoneStatus;
use App\Models\Agreement;
use App\Models\User;
use App\Notifications\Agreements\ChangesRequested;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Answers "Request changes" by writing the next version of the contract.
 *
 * The alternative — reopening the current agreement for editing — would let
 * terms move underneath a signature that has already been given, which makes
 * the contract log worthless. So the standing version is marked superseded and
 * a copy is drafted at version + 1 with no signatures on it. Both sides sign
 * again, and the old version stays readable exactly as it was signed.
 *
 * This is also how the vision document's "manages changes when additional
 * requirements are requested" works in practice: a new milestone added in v2
 * is a new priced row, not an edit to something already agreed.
 */
class SupersedeAgreement
{
    /**
     * Draft the next version of an agreement and retire the current one.
     */
    public function handle(Agreement $agreement, User $requestedBy, string $note): Agreement
    {
        return DB::transaction(function () use ($agreement, $requestedBy, $note): Agreement {
            $successor = Agreement::query()->create([
                'project_id' => $agreement->project_id,
                'application_id' => $agreement->application_id,
                'team_id' => $agreement->team_id,
                'student_id' => $agreement->student_id,
                /* Same document, next revision — the reference does not change. */
                'reference' => $agreement->reference,
                'version' => $agreement->version + 1,
                'status' => AgreementStatus::Draft,
                'scope_summary' => $agreement->scope_summary,
                'deliverables' => $agreement->deliverables,
                'intellectual_property_terms' => $agreement->intellectual_property_terms,
                'confidentiality_terms' => $agreement->confidentiality_terms,
                'academic_terms' => $agreement->academic_terms,
                'starts_on' => $agreement->starts_on,
                'ends_on' => $agreement->ends_on,
                'total_amount' => $agreement->total_amount,
            ]);

            foreach ($agreement->milestones as $milestone) {
                $successor->milestones()->create([
                    'position' => $milestone->position,
                    'title' => $milestone->title,
                    'description' => $milestone->description,
                    'amount' => $milestone->amount,
                    'starts_on' => $milestone->starts_on,
                    'ends_on' => $milestone->ends_on,
                    /*
                     * Work already signed off does not un-happen because the
                     * terms were revised, so approved milestones carry their
                     * status across. Everything else starts clean.
                     */
                    'status' => $milestone->status->isFinal()
                        ? $milestone->status
                        : MilestoneStatus::Pending,
                    'approved_at' => $milestone->approved_at,
                    'approved_by' => $milestone->approved_by,
                ]);
            }

            $agreement->update([
                'status' => AgreementStatus::Superseded,
                'superseded_by' => $successor->id,
            ]);

            $this->notifyCounterparty($agreement, $requestedBy, $note);

            return $successor->load('milestones');
        });
    }

    /**
     * Tell whoever did not ask for the change that the terms have reopened.
     */
    protected function notifyCounterparty(Agreement $agreement, User $requestedBy, string $note): void
    {
        if ($requestedBy->id === $agreement->student_id) {
            Notification::send(
                $agreement->team->members,
                new ChangesRequested($agreement, $requestedBy, $note),
            );

            return;
        }

        $agreement->student->notify(new ChangesRequested($agreement, $requestedBy, $note));
    }
}
