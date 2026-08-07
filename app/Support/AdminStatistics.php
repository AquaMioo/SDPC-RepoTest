<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

class AdminStatistics
{
    /**
     * Build the figures shared by the admin dashboard and overview screens.
     *
     * Everything here is derived from the users table. Collaboration and
     * project counters are intentionally absent until those models exist —
     * the screens render them as "not tracked yet" rather than inventing data.
     *
     * @return array{
     *     totalUsers: int,
     *     byStatus: array<string, int>,
     *     byRole: array<string, int>,
     *     approvedPercentage: int,
     *     pendingReview: int,
     *     deactivated: int
     * }
     */
    public function all(): array
    {
        $byStatus = $this->statusCounts();
        $byRole = $this->roleCounts();

        $total = array_sum($byStatus);
        $approved = $byStatus[UserStatus::Approved->value] ?? 0;

        return [
            'totalUsers' => $total,
            'byStatus' => $this->fill($byStatus, UserStatus::values()),
            'byRole' => $this->fill($byRole, UserRole::values()),
            'approvedPercentage' => $total > 0 ? (int) round($approved / $total * 100) : 0,
            'pendingReview' => $byStatus[UserStatus::Pending->value] ?? 0,
            'deactivated' => $byStatus[UserStatus::Deactivated->value] ?? 0,
        ];
    }

    /**
     * Count users grouped by account status.
     *
     * The column names are written out rather than interpolated so no part of
     * this raw fragment can ever come from outside the class.
     *
     * @return array<string, int>
     */
    private function statusCounts(): array
    {
        /** @var array<string, int> $counts */
        $counts = User::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        return array_map(intval(...), $counts);
    }

    /**
     * Count users grouped by role.
     *
     * @return array<string, int>
     */
    private function roleCounts(): array
    {
        /** @var array<string, int> $counts */
        $counts = User::query()
            ->selectRaw('role, count(*) as aggregate')
            ->groupBy('role')
            ->pluck('aggregate', 'role')
            ->all();

        return array_map(intval(...), $counts);
    }

    /**
     * Make sure every known key is present, even when its count is zero.
     *
     * @param  array<string, int>  $counts
     * @param  array<int, string>  $keys
     * @return array<string, int>
     */
    private function fill(array $counts, array $keys): array
    {
        $filled = [];

        foreach ($keys as $key) {
            $filled[$key] = $counts[$key] ?? 0;
        }

        return $filled;
    }
}
