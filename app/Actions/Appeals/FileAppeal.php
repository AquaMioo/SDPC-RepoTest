<?php

namespace App\Actions\Appeals;

use App\Enums\AppealStatus;
use App\Models\Appeal;
use App\Models\User;

/**
 * Records an account's answer to a decision taken about it.
 *
 * Two screens reach this: monitored and deactivated accounts write their
 * appeal from settings once signed in, and the guest page at /appeal takes one
 * from an account that cannot sign in (a forgotten password, say). Both file
 * the same row, which is why the rule about how many may be open lives here
 * rather than in either controller.
 */
class FileAppeal
{
    /**
     * File an appeal, unless this account has already filed one.
     *
     * Returns null when an appeal already exists, decided or not: each
     * account gets one appeal (testers, 2026-10-01). It used to be one open
     * appeal at a time, which let an account file again the moment the first
     * was decided.
     */
    public function handle(User $user, string $body): ?Appeal
    {
        if ($user->hasFiledAppeal()) {
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
