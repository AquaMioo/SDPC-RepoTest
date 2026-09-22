<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

/*
 * A safety net under the queue worker.
 *
 * Eleven notifications are queued (application received, student accepted,
 * agreement signed, team invitations...), so a dead worker means none of them
 * arrive, silently. On the self-hosted PC the worker is an NSSM service, and
 * NSSM does not restart a program that exits cleanly — which `queue:work
 * --max-time` does every hour. It was found dead on 2026-09-19 and again on
 * 2026-09-22 while the scheduler beside it kept running. This drains whatever
 * is waiting each minute and exits when the queue is empty, so a dead worker
 * delays a notification by a minute instead of forever. Beside a healthy
 * worker it simply finds nothing to do. Production only: `composer run dev`
 * runs its own listener, and a test calling schedule:run must not start one.
 */
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->environments('production')
    ->withoutOverlapping(5)
    ->description('Drain the queue in case its worker has stopped');
