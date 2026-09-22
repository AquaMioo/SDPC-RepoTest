<?php

namespace App\Actions\Student;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;

/**
 * Every application a student has sent, newest first, shaped for the screen.
 *
 * Lifted out of the old Workflow screen when it became Project Management:
 * the applications list lives on as a section of that page, and is its main
 * content until the student is collaborating with a client.
 */
class ListStudentApplications
{
    /**
     * List the student's applications.
     *
     * Accepted ones stay in the list as history — the monitoring half of the
     * page shows the work, this half shows what was asked and what came back.
     *
     * @return list<array<string, mixed>>
     */
    public function handle(User $student): array
    {
        /* One project at a time: an invitation cannot be taken while one is in hand. */
        $holdsProject = $student->isLockedToProject();

        return Application::query()
            ->where('user_id', $student->id)
            ->with(['project.team.clientProfile'])
            ->latest()
            ->get()
            ->map(fn (Application $application): array => [
                'id' => $application->id,
                'projectId' => $application->project->id,
                'projectTitle' => $application->project->title,
                'projectSlug' => $application->project->slug,
                /*
                 * An application row is what lets a thread exist, so a student
                 * can open one on anything still live between the two of them.
                 * A rejected or withdrawn application is a closed door, and a
                 * message is not the way to reopen it.
                 */
                'canMessage' => in_array($application->status, [
                    ApplicationStatus::Pending,
                    ApplicationStatus::Shortlisted,
                    ApplicationStatus::Accepted,
                ], true),
                'client' => $application->project->team->clientProfile?->business_name
                    ?? $application->project->team->name,
                'status' => $application->status->value,
                'statusLabel' => $application->status->label(),
                'source' => $application->source->label(),
                'appliedAt' => $application->created_at?->format('j M Y'),
                'respondedAt' => $application->responded_at?->format('j M Y'),
                /*
                 * An invitation is the student's to answer, so it offers
                 * Accept and Decline instead of Withdraw — there is nothing to
                 * take back from a conversation the client opened.
                 */
                'awaitsMyDecision' => $application->awaitsStudentDecision(),
                'canAccept' => $application->awaitsStudentDecision() && ! $holdsProject,
                /* Only an undecided application the student made can be taken back. */
                'canWithdraw' => $application->status->isActionable()
                    && ! $application->awaitsStudentDecision(),
            ])
            ->values()
            ->all();
    }
}
