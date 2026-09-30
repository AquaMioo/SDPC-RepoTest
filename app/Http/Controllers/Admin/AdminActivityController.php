<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminActivityFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminActivityController extends Controller
{
    /**
     * The administrator opened the bell: everything in it now counts as seen.
     */
    public function __invoke(Request $request, AdminActivityFeed $feed): RedirectResponse
    {
        $feed->markSeen($request->user());

        return back();
    }
}
