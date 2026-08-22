<?php

namespace App\Http\Controllers;

use App\Enums\IssueStatus;
use App\Http\Requests\ReportAccountRequest;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ReportController extends Controller
{
    /**
     * File a report about another account.
     *
     * Open to clients and students alike, and deliberately not behind
     * EnsureAccountIsVerified: someone being harassed by a verified account
     * must be able to say so before their own permit has been reviewed.
     *
     * A report is a statement, not a verdict — nothing here changes the
     * reported account. Only an administrator resolving the report does that.
     */
    public function store(ReportAccountRequest $request): RedirectResponse
    {
        $reported = (int) $request->validated('reported_user_id');

        /*
         * One open report per pair. Filing again while the first is still
         * waiting would let one account bury another's queue position, and an
         * administrator reading the same complaint five times learns nothing
         * they did not learn the first time.
         */
        $existing = Issue::query()
            ->open()
            ->where('reporter_id', $request->user()->id)
            ->where('reported_user_id', $reported)
            ->exists();

        if ($existing) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('You already have a report about this account waiting for review.'),
            ]);

            return back();
        }

        Issue::create([
            'reporter_id' => $request->user()->id,
            'reported_user_id' => $reported,
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
}
