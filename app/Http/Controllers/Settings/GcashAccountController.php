<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveGcashAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Settings → GCash account, for clients and students.
 *
 * Registered once and imported into every Project Extension Addendum the
 * account signs (SignAddendum keeps a copy with each signature). Private: it
 * appears on no profile, and every screen — this one included — shows it
 * masked (09******297). Stored encrypted.
 */
class GcashAccountController extends Controller
{
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
