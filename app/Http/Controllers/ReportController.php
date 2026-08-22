<?php

namespace App\Http\Controllers;

use App\Enums\IssueStatus;
use App\Http\Requests\ReportAccountRequest;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ReportController extends Controller
{
    /**
     * File a report about another account, or about one of its postings.
     *
     * Open to clients and students alike, and deliberately not behind
     * EnsureAccountIsVerified: someone being harassed by a verified account
     * must be able to say so before their own permit has been reviewed.
     *
     * A report is a statement, not a verdict — nothing here changes the
     * reported account or takes a posting off the board. Only an administrator
     * resolving the report does that.
     */
    public function store(ReportAccountRequest $request): RedirectResponse
    {
        $project = $request->reportedProject();

        /*
         * A posting report still names an account. Closing the posting alone
         * leaves whoever wrote it free to write another, so the administrator
         * needs somebody to act on either way.
         */
        $reported = $project !== null
            ? $this->accountBehind($project)
            : (int) $request->validated('reported_user_id');

        if ($reported === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('That posting has no account left to report.'),
            ]);

            return back();
        }

        /*
         * One open report per subject. Filing again while the first is still
         * waiting would let one account bury another's queue position, and an
         * administrator reading the same complaint five times learns nothing
         * they did not learn the first time. The posting is part of the key, so
         * reporting an account and reporting one of its postings stay separate
         * complaints.
         */
        $existing = Issue::query()
            ->open()
            ->where('reporter_id', $request->user()->id)
            ->where('reported_user_id', $reported)
            ->where('reported_project_id', $project?->id)
            ->exists();

        if ($existing) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $project !== null
                    ? __('You already have a report about this posting waiting for review.')
                    : __('You already have a report about this account waiting for review.'),
            ]);

            return back();
        }

        Issue::create([
            'reporter_id' => $request->user()->id,
            'reported_user_id' => $reported,
            'reported_project_id' => $project?->id,
            'category' => $request->validated('category'),
            'description' => $request->validated('description'),
            'status' => IssueStatus::Pending,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Report submitted. An administrator will review it.'),
        ]);

        return back();
    }

    /**
     * Find the account answerable for a posting.
     *
     * The author first, because they wrote it. A posting whose author has since
     * left falls back to whoever owns the team, which is the account the work
     * was published under.
     */
    private function accountBehind(Project $project): ?int
    {
        if ($project->created_by !== null) {
            return $project->created_by;
        }

        $owner = $project->team->owner();

        return $owner instanceof User ? $owner->id : null;
    }
}
