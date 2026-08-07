<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\RespondToApplication;
use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\InviteStudentRequest;
use App\Http\Requests\Client\RespondToApplicationRequest;
use App\Models\Application;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectApplicationController extends Controller
{
    /**
     * Review the applicants on a posting.
     */
    public function index(Team $currentTeam, Project $project): Response
    {
        Gate::authorize('viewApplicants', $project);

        $applications = $project->applications()
            ->with(['student.studentProfile.school', 'student.studentProfile.course', 'student.studentProfile.skills'])
            ->latest()
            ->get()
            ->map(fn (Application $application) => $this->toApplicant($application));

        return Inertia::render('client/projects/applicants', [
            'project' => [
                'slug' => $project->slug,
                'title' => $project->title,
                'teamSize' => $project->team_size,
                'isAcceptingApplications' => $project->isAcceptingApplications(),
            ],
            'applications' => $applications,
            'groups' => $applications->groupBy('status')->map->count(),
        ]);
    }

    /**
     * Accept, reject or shortlist an applicant.
     */
    public function update(
        RespondToApplicationRequest $request,
        Team $currentTeam,
        Application $application,
        RespondToApplication $respondToApplication,
    ): RedirectResponse {
        $status = $request->status();

        $respondToApplication->handle($application, $status, $request->user());

        return back()->with('success', match ($status) {
            ApplicationStatus::Accepted => 'Applicant accepted.',
            ApplicationStatus::Rejected => 'Applicant rejected.',
            ApplicationStatus::Shortlisted => 'Applicant shortlisted.',
            default => 'Application updated.',
        });
    }

    /**
     * Invite a student directly onto a posting.
     */
    public function invite(InviteStudentRequest $request, Team $currentTeam, Project $project): RedirectResponse
    {
        $project->applications()->create([
            'user_id' => $request->integer('user_id'),
            'status' => ApplicationStatus::Pending,
            'source' => ApplicationSource::Invited,
        ]);

        return back()->with('success', 'Invitation sent.');
    }

    /**
     * Shape an application for the applicants screen.
     *
     * @return array<string, mixed>
     */
    protected function toApplicant(Application $application): array
    {
        $profile = $application->student->studentProfile;

        return [
            'id' => $application->id,
            'status' => $application->status->value,
            'statusLabel' => $application->status->label(),
            'isActionable' => $application->status->isActionable(),
            'source' => $application->source->value,
            'sourceLabel' => $application->source->label(),
            'coverLetter' => $application->cover_letter,
            'proposedRate' => $application->proposed_rate,
            'appliedAt' => $application->created_at?->toDateTimeString(),
            'student' => [
                'id' => $application->student->id,
                'name' => $application->student->name,
                'headline' => $profile?->headline,
                'school' => $profile?->school?->name,
                'course' => $profile?->course?->abbreviation ?? $profile?->course?->name,
                'yearLevel' => $profile?->year_level,
                'rating' => $profile?->rating_average,
                'completedProjects' => $profile?->completed_projects_count,
                'isAvailable' => $profile?->is_available ?? false,
                'skills' => $profile?->skills->pluck('name') ?? collect(),
            ],
        ];
    }
}
