<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminMonitoringController extends Controller
{
    /**
     * Show the accounts an administrator has put under watch.
     *
     * "Monitored" was a status with no consequence anywhere — a label an
     * administrator could set and then never see again. This is the screen
     * that gives it one: the accounts carrying it, gathered in a place that
     * can be checked, with the reports that are usually the reason they were
     * flagged.
     */
    public function __invoke(): Response
    {
        $monitored = User::query()
            ->where('status', UserStatus::Monitored)
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'email', 'role', 'status', 'avatar', 'avatar_path', 'updated_at']);

        /*
         * Open reports per account, counted in one query rather than one per
         * row. A monitored account with reports still waiting is the case an
         * administrator most needs to see.
         */
        $openReports = Issue::query()
            ->open()
            ->whereIn('reported_user_id', $monitored->pluck('id'))
            ->selectRaw('reported_user_id, COUNT(*) as total')
            ->groupBy('reported_user_id')
            ->pluck('total', 'reported_user_id');

        return Inertia::render('admin/monitoring', [
            'accounts' => $monitored
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatarUrl' => $user->avatarUrl(),
                    'email' => $user->email,
                    'roleLabel' => $user->role->label(),
                    'since' => $user->updated_at?->diffForHumans(),
                    'openReports' => (int) ($openReports[$user->id] ?? 0),
                ])
                ->values()
                ->all(),
        ]);
    }
}
