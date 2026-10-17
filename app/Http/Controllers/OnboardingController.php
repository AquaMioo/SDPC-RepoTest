<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The first-login walkthrough (owner, 2026-10-18) calls this when it is
 * finished or skipped, so it never shows again for the account.
 */
class OnboardingController extends Controller
{
    /**
     * Mark the walkthrough done.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['has_completed_onboarding' => true])->save();

        return back();
    }
}
