<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AdminContentController extends Controller
{
    /**
     * Show the content management screen.
     *
     * The editor and live preview are fully interactive, but nothing is
     * persisted yet: announcements, rules and policies need their own table,
     * which is a schema decision left to the project owner. Wire this up by
     * loading the stored values here and adding an update endpoint.
     */
    public function __invoke(): Response
    {
        return Inertia::render('admin/content');
    }
}
