<?php

namespace App\Http\Controllers\Student;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Workflow" — everything the student currently has in flight.
 *
 * Two halves: the work they were accepted onto, and the applications still
 * waiting on a client. Both read from the same applications table, which is
 * the only record of who is on what.
 */
class WorkflowController extends Controller
{
    /**
     * Show the student's active projects and open applications.
     */
    public function __invoke(Request $request): Response
    {
        $student = $request->user();

        return Inertia::render('student/workflow', [
            'projects' => $this->activeProjects($student),
            'applications' => $this->applications($student),
        ]);
    }

    /**
     * Get the projects the student was accepted onto and is still building.
     *
     * @return list<array<string, mixed>>
     */
    protected function activeProjects(User $student): array
    {
        return Project::query()
            ->whereHas('applications', fn ($query) => $query
                ->where('user_id', $student->id)
                ->where('status', ApplicationStatus::Accepted))
            ->active()
            ->with(['team.clientProfile'])
            ->latest('published_at')
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'slug' => $project->slug,
                'title' => $project->title,
                'client' => $project->team->clientProfile?->business_name ?? $project->team->name,
                'status' => $project->status->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * Get every application the student has sent, newest first.
     *
     * Accepted ones stay in the list as history — the project half above shows
     * the work, this half shows what was asked and what came back.
     *
     * @return list<array<string, mixed>>
     */
    protected function applications(User $student): array
    {
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
                /** Only an undecided application can be taken back. */
                'canWithdraw' => $application->status->isActionable(),
            ])
            ->values()
            ->all();
    }
}
