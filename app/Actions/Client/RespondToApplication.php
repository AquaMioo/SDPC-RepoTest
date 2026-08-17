<?php

namespace App\Actions\Client;

use App\Actions\Agreements\DraftAgreement;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use App\Notifications\Client\StudentAccepted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RespondToApplication
{
    /**
     * Create a new action instance.
     */
    public function __construct(private DraftAgreement $draftAgreement) {}

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
        if ($status === ApplicationStatus::Accepted && $application->student->holdsProjectInHand()) {
            throw ValidationException::withMessages([
                'status' => $application->student->name.' is already building another project and cannot take this one on yet.',
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
                 * Acceptance no longer starts the project. It produces the
                 * contract the two sides negotiate and sign, and the second
                 * signature is what moves the posting into progress — see
                 * App\Actions\Agreements\SignAgreement. The vision document
                 * puts the Terms and Agreements Form before collaboration
                 * begins, and this is that ordering made real.
                 */
                $this->draftAgreement->handle($application);
            }

            return $application->refresh();
        });
    }
}
