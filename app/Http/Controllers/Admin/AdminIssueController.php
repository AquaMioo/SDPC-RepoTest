<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IssueResolution;
use App\Enums\IssueStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResolveIssueRequest;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminIssueController extends Controller
{
    /**
     * Show the reports and issues screen.
     *
     * Open reports first, newest first within that, so the queue reads as work
     * to do rather than a history.
     */
    public function index(): Response
    {
        return Inertia::render('admin/issues', [
            'issues' => Issue::query()
                ->with([
                    'reporter:id,name',
                    'reportedUser:id,name,status',
                    'reportedProject:id,slug,title,status',
                    'handler:id,name',
                ])
                ->orderByRaw("CASE WHEN status = 'resolved' THEN 1 ELSE 0 END")
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Issue $issue): array => [
                    'id' => $issue->id,
                    'title' => $issue->category->label(),
                    'reporter' => $issue->reporter->name,
                    'reportedUser' => $issue->reportedUser->name,
                    'reportedUserStatus' => $issue->reportedUser->status->label(),
                    /* Null unless the complaint is about a posting. */
                    'reportedPosting' => $issue->reportedProject === null ? null : [
                        'title' => $issue->reportedProject->title,
                        'statusLabel' => $issue->reportedProject->status->label(),
                        'closed' => $issue->reportedProject->status === ProjectStatus::Closed,
                    ],
                    'reportedOn' => $issue->created_at?->format('d M Y'),
                    'description' => $issue->description,
                    'status' => $issue->status->label(),
                    'resolved' => $issue->isResolved(),
                    'resolution' => $issue->resolution,
                    'handledBy' => $issue->handler?->name,
                    'actions' => IssueResolution::optionsFor($issue->isAboutPosting()),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Resolve a report.
     *
     * Everything this can do to an account or a posting sets the very same
     * state the screen that owns it sets — UserStatus from the Users screen,
     * ProjectStatus from the posting queue — so "monitored", "deactivated" and
     * "closed" each mean one thing on this platform rather than two.
     */
    public function update(ResolveIssueRequest $request, Issue $issue): RedirectResponse
    {
        $action = $request->action();

        if (($status = $action->accountStatus()) !== null) {
            $reported = $issue->reportedUser;
            $reported->status = $status;
            $reported->save();
        }

        if ($action === IssueResolution::ClosePosting) {
            $posting = $issue->reportedProject;
            $posting->forceFill(['status' => ProjectStatus::Closed])->save();
        }

        /*
         * Assigned rather than mass-assigned: the resolution fields are the
         * administrator's own record and are deliberately not fillable, so an
         * update() array would drop them without complaint.
         */
        $issue->status = IssueStatus::Resolved;
        $issue->resolution = $action->resolution();
        $issue->handled_by = $request->user()->id;
        $issue->handled_at = now();
        $issue->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $this->confirmation($action, $issue),
        ]);

        return back();
    }

    /**
     * Say what was just done, naming whoever it was done to.
     */
    private function confirmation(IssueResolution $action, Issue $issue): string
    {
        return match ($action) {
            IssueResolution::Warn => __('The report was closed with a warning.'),
            IssueResolution::Monitor => __(':name is now under monitoring and the report is closed.', [
                'name' => $issue->reportedUser->name,
            ]),
            IssueResolution::RemoveAccess => __(':name has been deactivated and the report closed.', [
                'name' => $issue->reportedUser->name,
            ]),
            IssueResolution::ClosePosting => __('":title" has been taken off the board and the report closed.', [
                'title' => $issue->reportedProject->title,
            ]),
        };
    }
}
