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
     * File an appeal, unless one is already waiting on an administrator.
     *
     * Returns null while the account's current appeal is pending. Once it is
     * resolved (granted or denied) the account may appeal again (owner,
     * 2026-10-09; replaces the one-appeal-ever rule of 2026-10-01). Only
     * appeals filed since the latest deactivation count (User::currentAppeal),
     * so a resolved appeal from an earlier decision never resurfaces.
     */
    public function handle(User $user, string $body): ?Appeal
    {
        /* Asked fresh, never through a relation cached on the model. */
        $waiting = $user->appeals()
            ->where('status', AppealStatus::Pending)
            ->when($user->restricted_at, fn ($query, $since) => $query->where('created_at', '>=', $since))
            ->exists();

        if ($waiting) {
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
