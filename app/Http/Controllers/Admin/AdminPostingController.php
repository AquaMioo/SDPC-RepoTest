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

/**
 * Review of postings, the third queue alongside business permits and student
 * credentials.
 *
 * A client publishes into `pending_review`, not straight onto the board. This
 * decision is what moves it to `open`, which is the only status the student
 * board lists — without it a posting is written but never seen.
 *
 * The queue itself is drawn by the dashboard overview and built by
 * App\Support\AdminPostingQueue; this controller is only the decision.
 */
class AdminPostingController extends Controller
{
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
