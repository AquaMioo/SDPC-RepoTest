<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminLoginController extends Controller
{
    /**
     * Show the admin login screen.
     *
     * The credentials themselves are posted to Fortify's authenticated session
     * controller, so this portal reuses the exact same login pipeline
     * (throttling, two factor, session fixation protection) as the public one.
     */
    public function create(Request $request): Response
    {
        // No Google props: this portal is password only. Administrator accounts
        // are issued by the developers rather than self-served.
        return Inertia::render('admin/login', [
            'status' => $request->session()->get('status'),
        ]);
    }
}
