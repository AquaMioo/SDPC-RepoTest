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

class RespondToApplication
{
    /**
     * Move an application into the status the client chose.
     */
    public function handle(Application $application, ApplicationStatus $status, User $responder): Application
    {
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
     * Move the posting into progress once the requested team size is met.
     *
     * Intake closes at the same time so late applicants are not left waiting
     * on a decision that will never come.
     */
    protected function startProjectIfFullyStaffed(Application $application): void
    {
        $project = $application->project;

        $acceptedCount = $project->applications()
            ->where('status', ApplicationStatus::Accepted)
            ->count();

        if ($acceptedCount < $project->team_size) {
            return;
        }

        $previousStatus = $project->status;

        $project->update([
            'status' => ProjectStatus::InProgress,
            'applications_open' => false,
        ]);

        Notification::send(
            $project->team->members,
            new ProjectStatusChanged($project->refresh(), $previousStatus),
        );
    }
}
