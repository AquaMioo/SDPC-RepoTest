<?php

namespace App\Http\Controllers\Settings;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveGcashAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Billing & Payment → GCash account, for clients and students
 * (its own settings page since 2026-10-20, owner; it used to sit on Account).
 *
 * Registered once and imported into every Project Extension Addendum the
 * account signs (SignAddendum keeps a copy with each signature). Private: it
 * appears on no profile, and every screen — this one included — shows it
 * masked (09******297). Stored encrypted.
 */
class GcashAccountController extends Controller
{
    /**
     * Show Settings → Billing & Payment.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->hasRole(UserRole::Client, UserRole::Student), 403);

        return Inertia::render('settings/billing', [
            /* Only ever masked, even to its owner. */
            'gcash' => ['masked' => $user->maskedGcashNumber()],
        ]);
    }

    /**
     * Register or replace the account's GCash number.
     */
    public function update(SaveGcashAccountRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'gcash_number' => $request->validated('gcash_number'),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('GCash account saved.')]);

        return back();
    }

    /**
     * Remove the registered number. Addenda already signed keep their copy.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['gcash_number' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('GCash account removed.')]);

        return back();
    }
}
