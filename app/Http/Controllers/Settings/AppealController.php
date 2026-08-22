<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Appeals\FileAppeal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreAppealRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The appeal a monitored account writes about itself.
 *
 * Reached from Account Information, which is why it lives in settings: the
 * account can still sign in, so the place to answer a decision about it is the
 * same screen that shows the decision.
 *
 * Registered under the `auth`-only group rather than `auth` + `verified`, and
 * deliberately outside EnsureAccountIsNotMonitored — gating the appeal behind
 * the state being appealed would close the only door out of it.
 */
class AppealController extends Controller
{
    public function __construct(private readonly FileAppeal $fileAppeal) {}

    /**
     * File an appeal against the decision standing on this account.
     */
    public function store(StoreAppealRequest $request): RedirectResponse
    {
        $appeal = $this->fileAppeal->handle(
            $request->user(),
            (string) $request->validated('body'),
        );

        Inertia::flash('toast', $appeal === null
            ? ['type' => 'error', 'message' => __('You already have an appeal waiting for review.')]
            : ['type' => 'success', 'message' => __('Appeal submitted. An administrator will review it.')],
        );

        return back();
    }
}
