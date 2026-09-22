<?php

namespace App\Actions\Client;

use App\Actions\Agreements\DraftAgreement;
use App\Actions\Messaging\SeatTeammatesInProjectChat;
use App\Actions\Student\CloseOtherInvitations;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\Client\StudentAccepted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RespondToApplication
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private DraftAgreement $draftAgreement,
        private CloseOtherInvitations $closeOtherInvitations,
        private SeatTeammatesInProjectChat $seatTeammates,
    ) {}

    /**
     * Move an application into the status the client chose.
     */
    public function handle(Application $application, ApplicationStatus $status, User $responder): Application
    {
        /*
         * A student holds one build at a time. The cap binds at acceptance
         * rather than at signing, even though signing is what starts the work:
         * a student who has been accepted is spoken for, and letting a second
         * client accept them while the first agreement is being drawn up would
         * hand them two contracts to choose between.
         *
         * A client can still invite and shortlist somebody who is busy.
         */
        if ($status === ApplicationStatus::Accepted && $application->student->isLockedToProject()) {
            throw ValidationException::withMessages([
                /*
                 * Says what happened, what it means for them, and when it
                 * changes — a refusal that only says no leaves the client
                 * wondering whether to keep waiting on this student.
                 *
                 * "Committed to" rather than "signed for": the cap binds at
                 * acceptance, so this fires for a student whose agreement is
                 * still being drawn up and not yet signed by anybody.
                 */
                'status' => $application->student->name.' has already been taken on for another project, so they cannot be hired for this one yet. You can keep them shortlisted — they become available again once that build is finished.',
            ]);
        }

        return DB::transaction(function () use ($application, $status, $responder) {
            $application->update([
                'status' => $status,
                'responded_by' => $responder->id,
                'responded_at' => now(),
            ]);

            if ($status === ApplicationStatus::Accepted) {
                $application->student->notify(new StudentAccepted($application));

                /*
                 * Open the thread on acceptance, the way inviting already does
                 * in ProjectApplicationController.
                 *
                 * Only the invited path opened one before, so a student who
                 * applied and was hired had an empty inbox: the client who had
                 * just taken them on did not appear anywhere in Messages until
                 * one of the two thought to press Message on the posting. The
                 * introduction has happened by this point either way, so the
                 * thread should exist either way.
                 *
                 * firstOrCreate because the pair may already have one — a
                 * client who invited, was turned down, and later accepted an
                 * application from the same student on the same posting.
                 */
                Conversation::firstOrCreate([
                    'project_id' => $application->project_id,
                    'user_id' => $application->user_id,
                ]);

                /*
                 * The team the student already leads comes into that thread
                 * with them: they are all tied to this build from here on, so
                 * the client is talking to the group, not to one member of it.
                 */
                $this->seatTeammates->handle($application->student);

                /*
                 * Acceptance no longer starts the project. It produces the
                 * contract the two sides negotiate and sign, and the second
                 * signature is what moves the posting into progress — see
                 * App\Actions\Agreements\SignAgreement. The vision document
                 * puts the Terms and Agreements Form before collaboration
                 * begins, and this is that ordering made real.
                 */
                $this->draftAgreement->handle($application);

                /*
                 * The student is spoken for, so the invitations other
                 * businesses sent them are closed and those businesses told.
                 */
                $this->closeOtherInvitations->handle($application);
            }

            return $application->refresh();
        });
    }
}
