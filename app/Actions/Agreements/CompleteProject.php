<?php

namespace App\Actions\Agreements;

use App\Enums\AgreementStatus;
use App\Enums\ProjectStatus;
use App\Models\Agreement;
use App\Models\Project;
use App\Models\ProjectRating;
use App\Models\StudentProfile;
use App\Models\User;
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
 * Completing is also rating (owner, 2026-10-09): the client's 1-5 rating and
 * optional feedback are written, permanently, for every student on each
 * agreement completed — the signer and their teammates — whose
 * completed_projects_count goes up by one (HiringRule reads it) and whose
 * rating average is recalculated.
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
    public function handle(Agreement $agreement, int $rating, ?string $feedback, User $rater): Project
    {
        $completed = DB::transaction(function () use ($agreement, $rating, $feedback, $rater): array {
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

            $agreements->each(fn (Agreement $finished) => $this->rate($finished, $project, $rating, $feedback, $rater));

            return [$project, $previousStatus, $agreements->modelKeys()];
        });

        [$project, $previousStatus, $agreementIds] = $completed;

        $this->announce($project, $previousStatus, $agreementIds);

        return $project;
    }

    /**
     * Rate every student on one completed agreement, and count the build.
     */
    protected function rate(Agreement $agreement, Project $project, int $rating, ?string $feedback, User $rater): void
    {
        $clientName = $project->team->clientProfile?->business_name ?? $project->team->name;

        foreach ($agreement->studentSide() as $student) {
            ProjectRating::create([
                'agreement_id' => $agreement->id,
                'project_id' => $project->id,
                'student_id' => $student->id,
                'client_team_id' => $project->team_id,
                'rated_by' => $rater->id,
                'project_title' => $project->title,
                'client_name' => $clientName,
                'rating' => $rating,
                'feedback' => filled($feedback) ? $feedback : null,
            ]);

            $ratings = $student->ratingsReceived();

            StudentProfile::query()->where('user_id', $student->id)->update([
                'completed_projects_count' => DB::raw('completed_projects_count + 1'),
                'rating_average' => round((float) $ratings->avg('rating'), 2),
                'ratings_count' => $ratings->count(),
            ]);
        }
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
