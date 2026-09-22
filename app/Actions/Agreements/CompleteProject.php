<?php

namespace App\Actions\Agreements;

use App\Enums\AgreementStatus;
use App\Enums\ProjectStatus;
use App\Models\Agreement;
use App\Models\Project;
use App\Notifications\Agreements\ProjectCompleted;
use App\Notifications\Client\ProjectStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The client accepts the turnover and ends the collaboration.
 *
 * The project becomes Completed and every agreement still running on it
 * becomes Completed with it — a posting can carry one agreement per student
 * taken on, and they all end together.
 *
 * Releasing people needs nothing further. Both locks read the project's
 * status: User::isLockedToProject() and ProjectPolicy::create() only count
 * postings that are unfinished, so the students (signer and teammates) may
 * apply again and the business may post again from this commit on.
 *
 * Completed, not Archived. Archived means a posting the client withdrew
 * ("no longer listed"), and the landing page counts Completed projects as
 * delivered work. "Archiving" here is the project leaving every active screen
 * for the Completed list in Project Management, which stays readable.
 */
class CompleteProject
{
    /**
     * Complete the agreement's project.
     *
     * @throws ValidationException when the project is not in progress
     */
    public function handle(Agreement $agreement): Project
    {
        $completed = DB::transaction(function () use ($agreement): array {
            /* Locked, so two clicks on Complete cannot both pass the check. */
            $project = Project::query()->lockForUpdate()->findOrFail($agreement->project_id);

            if ($project->status !== ProjectStatus::InProgress) {
                throw ValidationException::withMessages([
                    'project' => __('Only a project in progress can be completed.'),
                ]);
            }

            $now = now();
            $previousStatus = $project->status;

            $project->update([
                'status' => ProjectStatus::Completed,
                'completed_at' => $now,
                'applications_open' => false,
            ]);

            $agreements = Agreement::query()
                ->where('project_id', $project->id)
                ->active()
                ->get();

            Agreement::query()
                ->whereKey($agreements->modelKeys())
                ->update([
                    'status' => AgreementStatus::Completed->value,
                    'completed_at' => $now,
                ]);

            return [$project, $previousStatus, $agreements->modelKeys()];
        });

        [$project, $previousStatus, $agreementIds] = $completed;

        $this->announce($project, $previousStatus, $agreementIds);

        return $project;
    }

    /**
     * Tell both sides, after the commit: the business through its members,
     * the student side through the signer and every teammate.
     *
     * @param  list<int>  $agreementIds
     */
    protected function announce(Project $project, ProjectStatus $previousStatus, array $agreementIds): void
    {
        Notification::send(
            $project->team->members,
            new ProjectStatusChanged($project->refresh(), $previousStatus),
        );

        Agreement::query()
            ->whereKey($agreementIds)
            ->with('project.team.clientProfile')
            ->get()
            ->each(fn (Agreement $agreement) => Notification::send(
                $agreement->studentSide(),
                new ProjectCompleted($agreement),
            ));
    }
}
