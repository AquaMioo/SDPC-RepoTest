<?php

namespace App\Http\Controllers\Client;

use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Project;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ClientDashboardController extends Controller
{
    /**
     * Show the client workspace overview.
     */
    public function __invoke(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        return Inertia::render('client/dashboard', [
            'stats' => $this->stats($team),
            'profileCompletion' => $team->clientProfile?->completionPercentage() ?? 0,
            /**
             * Clients no longer pass through the generic dashboard, so the
             * invitation prompt has to travel with them.
             */
            'pendingInvitations' => TeamInvitation::pendingForDashboard($request->user()->email),
            /**
             * Deferred so the cards paint immediately — these two panels are
             * the slowest queries on the screen and the least urgent.
             */
            'recentActivity' => Inertia::defer(fn () => $this->recentActivity($team)),
            'shortlistedStudents' => Inertia::defer(fn () => $this->shortlisted($team)),
        ]);
    }

    /**
     * Get the counts behind the dashboard cards.
     *
     * @return array<string, int>
     */
    protected function stats(Team $team): array
    {
        $projects = Project::query()->forTeam($team);

        return [
            'projectsPosted' => (clone $projects)->count(),
            'activeProjects' => (clone $projects)->active()->count(),
            'completedProjects' => (clone $projects)->where('status', ProjectStatus::Completed)->count(),
            'pendingApplications' => $this->applications($team)->awaitingDecision()->count(),
            'shortlistedStudents' => $this->applications($team)
                ->withStatus(ApplicationStatus::Shortlisted)
                ->count(),
            'acceptedStudents' => $this->applications($team)
                ->withStatus(ApplicationStatus::Accepted)
                ->count(),
        ];
    }

    /**
     * Get the most recent applications across the team's postings.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function recentActivity(Team $team)
    {
        return $this->applications($team)
            ->with(['student', 'project'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Application $application) => [
                'id' => $application->id,
                'studentName' => $application->student->name,
                'projectTitle' => $application->project->title,
                'projectSlug' => $application->project->slug,
                'status' => $application->status->value,
                'statusLabel' => $application->status->label(),
                'happenedAt' => $application->created_at?->diffForHumans(),
            ]);
    }

    /**
     * Get the students the team has shortlisted but not yet decided on.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function shortlisted(Team $team)
    {
        return $this->applications($team)
            ->withStatus(ApplicationStatus::Shortlisted)
            ->with(['student.studentProfile', 'project'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Application $application) => [
                'id' => $application->id,
                'studentId' => $application->student->id,
                'studentName' => $application->student->name,
                'headline' => $application->student->studentProfile?->headline,
                'projectTitle' => $application->project->title,
            ]);
    }

    /**
     * Start an application query scoped to the team's postings.
     *
     * @return Builder<Application>
     */
    protected function applications(Team $team)
    {
        return Application::query()
            ->whereHas('project', fn ($query) => $query->where('team_id', $team->id));
    }
}
