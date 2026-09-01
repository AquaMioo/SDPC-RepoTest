<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Logout;

/**
 * Signing out is immediate, not a five-minute fade.
 *
 * Without this the presence window would carry a signed-out account for as
 * long as it had left to run, which is exactly what QA reported: a student
 * logs out and the client's team panel still shows them here. Nulling the
 * stamp makes leaving deliberate, and leaves the window to do what it is
 * actually for — the tab closed without signing out.
 */
class ClearLastSeen
{
    public function handle(Logout $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        User::withoutTimestamps(
            fn () => $user->forceFill(['last_seen_at' => null])->saveQuietly(),
        );
    }
}
