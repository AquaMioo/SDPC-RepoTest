<?php

namespace App\Http\Controllers\Student;

use App\Enums\ProjectStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Client List" — the businesses a student can browse.
 *
 * The mirror of the client module's Recruit screen. Until now the traffic only
 * went one way: a client could open a student's profile, but a student could
 * not read anything about the business behind a posting beyond the one line
 * the board carries.
 *
 * Only administrator-verified businesses appear. An unverified business cannot
 * publish a posting either, so listing one here would advertise somebody a
 * student has no way to work with.
 */
class ClientDirectoryController extends Controller
{
    /**
     * List the businesses on the platform.
     */
    public function index(Request $request, Team $currentTeam): Response
    {
        $search = trim((string) $request->query('search', ''));

        $businesses = Team::query()
            ->whereHas('clientProfile', fn (Builder $query) => $query
                ->where('verification_status', VerificationStatus::Verified)
                ->when($search !== '', fn (Builder $inner) => $inner
                    ->where(fn (Builder $match) => $match
                        ->where('business_name', 'like', "%{$search}%")
                        ->orWhere('business_description', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%"))))
            ->with('clientProfile')
            ->withCount(['projects as open_postings_count' => fn (Builder $query) => $query
                ->where('status', ProjectStatus::Open)])
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(function (Team $team): array {
                $profile = $team->clientProfile;

                return [
                    'teamSlug' => $team->slug,
                    'businessName' => $profile->business_name ?? $team->name,
                    'description' => $profile?->business_description,
                    'city' => $profile?->city,
                    'province' => $profile?->province,
                    'openPostings' => (int) $team->getAttribute('open_postings_count'),
                ];
            });

        return Inertia::render('student/clients', [
            'businesses' => $businesses,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Show one business.
     *
     * Deliberately without a phone number or an email address. Messaging on
     * this platform waits for an application to link the two sides — handing
     * out contact details here would route around that and leave every
     * verified business open to being cold-called.
     */
    public function show(Request $request, Team $currentTeam, Team $business): Response
    {
        $profile = $business->clientProfile;

        abort_if($profile === null || ! $profile->isVerified(), 404);

        return Inertia::render('student/client', [
            'business' => [
                'teamSlug' => $business->slug,
                'businessName' => $profile->business_name,
                'description' => $profile->business_description,
                'ownerName' => $profile->owner_name,
                'city' => $profile->city,
                'province' => $profile->province,
                'websiteUrl' => $profile->website_url,
                'facebookUrl' => $profile->facebook_url,
                'verifiedAt' => $profile->verified_at?->toFormattedDateString(),
            ],
            'postings' => Project::query()
                ->where('team_id', $business->id)
                ->where('status', ProjectStatus::Open)
                ->with('skills')
                ->latest('published_at')
                ->get()
                ->map(fn (Project $project): array => [
                    'slug' => $project->slug,
                    'title' => $project->title,
                    'category' => $project->category,
                    'industry' => $project->industry,
                    'isAcceptingApplications' => $project->applications_open,
                    'skills' => $project->skills->pluck('name')->values()->all(),
                ])
                ->values()
                ->all(),
        ]);
    }
}
