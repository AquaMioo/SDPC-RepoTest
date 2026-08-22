<?php

namespace App\Actions\Appeals;

use App\Enums\AppealStatus;
use App\Models\Appeal;
use App\Models\User;

/**
 * Records an account's answer to a decision taken about it.
 *
 * Two screens reach this: a monitored account writes its appeal from settings,
 * and a deactivated one from the guest page — it cannot sign in to reach
 * settings at all. Both file the same row, which is why the rule about how
 * many may be open lives here rather than in either controller.
 */
class FileAppeal
{
    /**
     * File an appeal, unless one from this account is already unread.
     *
     * Returns null when there is already an open appeal. Filing again while
     * the first is waiting would let an account bury its own queue position,
     * and an administrator reading the same plea five times learns nothing
     * they did not learn the first time.
     */
    public function handle(User $user, string $body): ?Appeal
    {
        if ($user->hasPendingAppeal()) {
            return null;
        }

        return Appeal::create([
            'user_id' => $user->id,
            /*
             * Snapshotted, because granting the appeal changes the status it
             * was written about — without this a granted appeal reads as an
             * argument against nothing.
             */
            'account_status' => $user->status,
            'body' => $body,
            'status' => AppealStatus::Pending,
        ]);
    }
}
