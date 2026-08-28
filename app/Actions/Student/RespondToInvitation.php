<?php

namespace App\Actions\Student;

use App\Actions\Agreements\DraftAgreement;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Notifications\Client\InvitationAccepted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The student's half of RespondToApplication.
 *
 * A client inviting a student has already said yes, so the invitation is
 * answered by the student rather than by the business that sent it. Accepting
 * has to land in exactly the same place as a client accepting an application —
 * same status, same one-project cap, same drafted agreement — or an invited
 * student would reach the contract stage by a different route with different
 * rules.
 */
class RespondToInvitation
{
    /**
     * Create a new action instance.
     */
    public function __construct(private DraftAgreement $draftAgreement) {}

    /**
     * Take the invitation up.
     */
    public function accept(Application $application): Application
    {
        /*
         * The same cap RespondToApplication enforces, asked from the other
         * side: a student already building something cannot take a second
         * project on, however the offer reached them. See
         * .ai/rules/actions-client.md.
         */
        if ($application->student->holdsProjectInHand()) {
            throw ValidationException::withMessages([
                'application' => 'You are already building another project, so you cannot take this one on yet.',
            ]);
        }

        return DB::transaction(function () use ($application): Application {
            $application->update([
                'status' => ApplicationStatus::Accepted,
                /* The student decided it, so the student is the responder. */
                'responded_by' => $application->user_id,
                'responded_at' => now(),
            ]);

            Notification::send(
                $application->project->team->members,
                new InvitationAccepted($application),
            );

            /* Acceptance produces the contract, not the start of the work. */
            $this->draftAgreement->handle($application);

            return $application->refresh();
        });
    }

    /**
     * Turn the invitation down.
     *
     * Recorded as Withdrawn rather than Rejected: both are terminal, and
     * Rejected is the client's word for a decision they made. This one is the
     * student's.
     */
    public function decline(Application $application): Application
    {
        $application->update([
            'status' => ApplicationStatus::Withdrawn,
            'responded_by' => $application->user_id,
            'responded_at' => now(),
        ]);

        return $application->refresh();
    }
}
