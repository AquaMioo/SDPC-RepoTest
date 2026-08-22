<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteContent;
use App\Support\AdminPostingQueue;
use App\Support\AdminStatistics;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dashboard overview — the administrator's one working screen.
 *
 * Postings review and content management used to be screens of their own. The
 * scope names neither: the developers module is Login, Dashboard Overview,
 * User Account Management, Content Management and Report and Issues
 * Management. So the posting queue folded into content management, and content
 * management folded into here. Both keep their own write endpoints
 * (AdminPostingController::update, AdminContentController::update) — only the
 * screens were merged.
 */
class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminStatistics $statistics,
        private readonly AdminPostingQueue $postings,
    ) {}

    /**
     * Show the admin welcome dashboard.
     */
    public function __invoke(): Response
    {
        return Inertia::render('admin/dashboard', [
            'stats' => $this->statistics->all(),
            'postings' => $this->postings->all(),
            'content' => SiteContent::allKeyed(),
        ]);
    }
}
