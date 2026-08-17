<?php

namespace App\Actions\Client;

use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Models\Application;
use App\Models\User;
use App\Notifications\Client\ProjectStatusChanged;
use App\Notifications\Client\StudentAccepted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class RespondToApplication
{
    /**
     * Move an application into the status the client chose.
     */
    public function handle(Application $application, ApplicationStatus $status, User $responder): Application
    {
        /*
         * A student holds one build at a time, and acceptance is the moment
         * work starts — so the cap is enforced here rather than only where a
         * student applies. A client can still invite and shortlist somebody
         * who is busy; they just cannot put them on a second project.
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

                $this->startProjectIfFullyStaffed($application);
            }

            return $application->refresh();
        });
    }

    /**
     * Move the posting into progress on the first acceptance.
     *
     * A posting no longer states how many students it wants, so there is no
     * headcount to be "fully staffed" against. Accepting somebody is the
     * moment the work starts, and intake stays open afterwards — the client
     * may still want more people, and can pause applications themselves.
     */
    protected function startProjectIfFullyStaffed(Application $application): void
    {
        $project = $application->project;

        $acceptedCount = $project->applications()
            ->where('status', ApplicationStatus::Accepted)
            ->count();

        if ($acceptedCount !== 1 || $project->status === ProjectStatus::InProgress) {
            return;
        }

        $previousStatus = $project->status;

        $project->update(['status' => ProjectStatus::InProgress]);

        Notification::send(
            $project->team->members,
            new ProjectStatusChanged($project->refresh(), $previousStatus),
        );
    }
}
