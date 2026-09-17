<?php

namespace App\Actions\Student;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Notifications\Client\InvitationClosed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Close a student's other invitations once they have been taken on.
 *
 * A student builds one project at a time (User::holdsProjectInHand), so the
 * moment one client has them — by the student accepting an invitation, or by
 * a client accepting their application — every other invitation still open
 * can no longer be taken up. Leaving them open had those clients waiting on
 * an answer that could not come. They are closed here, and each business that
 * sent one is told by email and in the bell.
 *
 * Closed as Withdrawn with the student as the responder: taking another offer
 * is the student's decision, the same word RespondToInvitation::decline uses.
 */
class CloseOtherInvitations
{
    /**
     * Close the invitations and tell the businesses that sent them.
     *
     * @return int how many invitations were closed
     */
    public function handle(Application $taken): int
    {
        $student = $taken->student;

        $others = Application::query()
            ->openInvitationsFor($student)
            ->whereKeyNot($taken->id)
            ->with('project.team.members')
            ->get();

        foreach ($others as $invitation) {
            $invitation->update([
                'status' => ApplicationStatus::Withdrawn,
                'responded_by' => $student->id,
                'responded_at' => now(),
            ]);

            /*
             * One business at a time, and never allowed to fail the
             * acceptance: the student is already taken on, and a mail server
             * being down must not undo that.
             */
            try {
                Notification::send(
                    $invitation->project->team->members,
                    new InvitationClosed($invitation, $taken),
                );
            } catch (Throwable $exception) {
                Log::warning('An invitation was closed but its business could not be told.', [
                    'application_id' => $invitation->id,
                    'reason' => $exception->getMessage(),
                ]);
            }
        }

        return $others->count();
    }
}
