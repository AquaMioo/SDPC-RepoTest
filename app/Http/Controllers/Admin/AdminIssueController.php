<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IssueStatus;
use App\Enums\UserStatus;
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
                ->with(['reporter:id,name', 'reportedUser:id,name,status', 'handler:id,name'])
                ->orderByRaw("CASE WHEN status = 'resolved' THEN 1 ELSE 0 END")
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Issue $issue): array => [
                    'id' => $issue->id,
                    'title' => $issue->category->label(),
                    'reporter' => $issue->reporter->name,
                    'reportedUser' => $issue->reportedUser->name,
                    'reportedUserStatus' => $issue->reportedUser->status->label(),
                    'reportedOn' => $issue->created_at?->format('d M Y'),
                    'description' => $issue->description,
                    'status' => $issue->status->label(),
                    'resolved' => $issue->isResolved(),
                    'resolution' => $issue->resolution,
                    'handledBy' => $issue->handler?->name,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Resolve a report, either with a warning or by revoking access.
     *
     * Removing access is the only action here that touches the reported
     * account, and it sets the same UserStatus the Users screen sets, so
     * "deactivated" means one thing on this platform rather than two.
     */
    public function update(ResolveIssueRequest $request, Issue $issue): RedirectResponse
    {
        $action = $request->validated('action');

        if ($action === 'remove_access') {
            $reported = $issue->reportedUser;
            $reported->status = UserStatus::Deactivated;
            $reported->save();
        }

        /*
         * Assigned rather than mass-assigned: the resolution fields are the
         * administrator's own record and are deliberately not fillable, so an
         * update() array would drop them without complaint.
         */
        $issue->status = IssueStatus::Resolved;
        $issue->resolution = $action === 'remove_access' ? 'Access removed' : 'Warned';
        $issue->handled_by = $request->user()->id;
        $issue->handled_at = now();
        $issue->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $action === 'remove_access'
                ? __(':name has been deactivated and the report closed.', ['name' => $issue->reportedUser->name])
                : __('The report was closed with a warning.'),
        ]);

        return back();
    }
}
