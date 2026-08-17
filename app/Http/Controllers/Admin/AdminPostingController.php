<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Notifications\Client\ProjectStatusChanged;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Review of postings, the third queue alongside business permits and student
 * credentials.
 *
 * A client publishes into `pending_review`, not straight onto the board. This
 * screen is what moves it to `open`, which is the only status the student
 * board lists — without a decision here a posting is written but never seen.
 */
class AdminPostingController extends Controller
{
    /**
     * Show every posting that has been submitted, waiting ones first.
     */
    public function index(): Response
    {
        $postings = Project::query()
            ->with(['team.clientProfile', 'skills'])
            ->whereNot('status', ProjectStatus::Draft)
            ->latest('published_at')
            ->get()
            ->sortBy(fn (Project $project): int => $project->status === ProjectStatus::PendingReview ? 0 : 1)
            ->values()
            ->map(fn (Project $project): array => [
                'slug' => $project->slug,
                'title' => $project->title,
                'description' => str($project->description)->limit(240)->toString(),
                'category' => $project->category,
                'business' => $project->team->clientProfile?->business_name ?? $project->team->name,
                'city' => $project->team->clientProfile?->city,
                'skills' => $project->skills->pluck('name')->all(),
                'status' => $project->status->value,
                'statusLabel' => $project->status->label(),
                'publishedAt' => $project->published_at?->diffForHumans(),
                'awaitingDecision' => $project->status === ProjectStatus::PendingReview,
            ])
            ->all();

        return Inertia::render('admin/postings', [
            'postings' => $postings,
        ]);
    }

    /**
     * Record the administrator's decision on a posting.
     *
     * Approving is what puts it in front of students; closing takes it back
     * off the board without deleting the client's work.
     */
    public function update(Request $request, Project $posting): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                ProjectStatus::Open->value,
                ProjectStatus::Closed->value,
            ])],
        ]);

        $previous = $posting->status;
        $status = ProjectStatus::from($validated['status']);

        if ($previous === $status) {
            return back();
        }

        $posting->forceFill([
            'status' => $status,
            /* An approval is also the moment it becomes publicly visible. */
            'published_at' => $posting->published_at ?? now(),
        ])->save();

        Notification::send(
            $posting->team->members,
            new ProjectStatusChanged($posting, $previous),
        );

        return back()->with(
            'success',
            $status === ProjectStatus::Open
                ? 'Posting approved and is now on the student board.'
                : 'Posting closed and taken off the student board.',
        );
    }
}
