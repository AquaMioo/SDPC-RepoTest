<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AdminIssueController extends Controller
{
    /**
     * Show the reports and issues screen.
     *
     * Reports are rendered from client-side sample data until an issues table
     * exists. The warn / remove-access actions are wired to the confirmation
     * flow but do not reach the database yet.
     */
    public function __invoke(): Response
    {
        return Inertia::render('admin/issues');
    }
}
