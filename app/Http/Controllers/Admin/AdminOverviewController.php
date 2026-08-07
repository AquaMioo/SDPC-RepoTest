<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminStatistics;
use Inertia\Inertia;
use Inertia\Response;

class AdminOverviewController extends Controller
{
    public function __construct(private readonly AdminStatistics $statistics) {}

    /**
     * Show the dashboard overview strip.
     */
    public function __invoke(): Response
    {
        return Inertia::render('admin/overview', [
            'stats' => $this->statistics->all(),
            'recentUsers' => User::query()
                ->whereIn('role', [UserRole::Student, UserRole::Client])
                ->latest()
                ->limit(4)
                ->get(['id', 'name', 'role'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'roleLabel' => $user->role->label(),
                ])
                ->values()
                ->all(),
        ]);
    }
}
