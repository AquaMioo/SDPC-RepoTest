<?php

namespace App\Http\Controllers\Client;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
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

        $profile->load(['school', 'course', 'skills']);

        return Inertia::render('client/students/show', [
            'student' => [
                'id' => $user->id,
                'name' => $user->name,
                'headline' => $profile->headline,
                'biography' => $profile->biography,
                'school' => $profile->school?->name,
                'course' => $profile->course?->name,
                'yearLevel' => $profile->year_level,
                'githubUrl' => $profile->github_url,
                'portfolioUrl' => $profile->portfolio_url,
                'isAvailable' => $profile->is_available,
                'hourlyRate' => $profile->hourly_rate,
                'rating' => (float) $profile->rating_average,
                'completedProjects' => $profile->completed_projects_count,
                'skills' => $profile->skills->map->only(['name', 'type']),
            ],
            /** Lets the profile screen show "already invited" instead of a dead invite button. */
            'existingApplications' => $user->applications()
                ->whereHas('project', fn ($query) => $query->where('team_id', $request->user()->current_team_id))
                ->with('project:id,slug,title')
                ->get()
                ->map(fn ($application) => [
                    'projectSlug' => $application->project->slug,
                    'projectTitle' => $application->project->title,
                    'status' => $application->status->value,
                    'statusLabel' => $application->status->label(),
                    'isAccepted' => $application->status === ApplicationStatus::Accepted,
                ]),
        ]);
    }
}
