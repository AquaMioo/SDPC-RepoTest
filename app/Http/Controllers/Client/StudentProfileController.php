<?php

namespace App\Http\Controllers\Client;

use App\Enums\ApplicationStatus;
use App\Enums\IssueCategory;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Project;
use App\Models\StudentPortfolioItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentProfileController extends Controller
{
    /**
     * Show a student's public profile to a prospective client.
     */
    public function __invoke(Request $request, Team $currentTeam, User $user): Response
    {
        abort_unless($user->hasRole(UserRole::Student), 404);

        $profile = $user->studentProfile;

        abort_if($profile === null, 404);

        $profile->load(['school', 'course', 'skills', 'portfolioItems.skills']);

        $links = $user->applications()
            ->whereHas('project', fn ($query) => $query->where('team_id', $request->user()->current_team_id))
            ->with('project:id,slug,title')
            ->get();

        return Inertia::render('client/students/show', [
            'student' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatarUrl' => $user->avatarUrl(),
                'headline' => $profile->headline,
                'biography' => $profile->biography,
                'school' => $profile->school?->name,
                'course' => $profile->course?->name,
                'yearLevel' => $profile->year_level,
                'githubUrl' => $profile->github_url,
                'portfolioUrl' => $profile->portfolio_url,
                'isAvailable' => $profile->is_available,
                /** Deliberately not sent — see RecruitController::toCard(). */
                'rating' => (float) $profile->rating_average,
                'completedProjects' => $profile->completed_projects_count,
                'skills' => $profile->skills->map->only(['name', 'type']),
                // Composed, so a client reads "Towerville, Barangay Muzon,
                // San Jose Del Monte" rather than just the free-text half.
                'location' => $profile->displayLocation(),
                'weeklyHours' => $profile->weekly_hours,
                'availabilityNote' => $profile->availability_note,
                'responseTimeHours' => $profile->response_time_hours,
                'hourlyRate' => $profile->hourly_rate,
                'educationNote' => $profile->education_note,
                /*
                 * Student Background History. This is the evidence a client
                 * reads before hiring, and until the student module could
                 * write it there was nothing here but a single link.
                 */
                'portfolio' => $profile->portfolioItems
                    ->map(fn (StudentPortfolioItem $item): array => [
                        'id' => $item->id,
                        'title' => $item->title,
                        'role' => $item->role,
                        'description' => $item->description,
                        'year' => $item->year,
                        'url' => $item->url,
                        'repositoryUrl' => $item->repository_url,
                        'skills' => $item->skills->pluck('name')->values()->all(),
                    ])
                    ->values()
                    ->all(),
            ],
            /** Lets the profile screen show "already invited" instead of a dead invite button. */
            'existingApplications' => $links->map(fn (Application $application) => [
                'projectId' => $application->project->id,
                'projectSlug' => $application->project->slug,
                'projectTitle' => $application->project->title,
                'status' => $application->status->value,
                'statusLabel' => $application->status->label(),
                'isAccepted' => $application->status === ApplicationStatus::Accepted,
            ])->values(),

            /*
             * The postings this student is not already on. Without these the
             * screen could show a profile but offer nothing to do with it,
             * which is what the recruit flow used to dead-end into.
             */
            'invitableProjects' => Project::query()
                ->forTeam($request->user()->currentTeam)
                ->whereIn('status', [ProjectStatus::Open, ProjectStatus::PendingReview, ProjectStatus::InProgress])
                ->whereNotIn('id', $links->pluck('project.id'))
                ->orderBy('title')
                ->get(['id', 'slug', 'title'])
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'slug' => $project->slug,
                    'title' => $project->title,
                ])
                ->values(),

            'canInvite' => $request->user()->isVerifiedForOperating(),

            'reportCategories' => IssueCategory::options(),
        ]);
    }
}
