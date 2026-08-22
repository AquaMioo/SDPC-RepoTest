<?php

use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

/*
 * The notification centre.
 *
 * No role middleware: both modules raise notifications and both read them from
 * the same bell, and a row already belongs to exactly one account. Scoping to
 * the acting team keeps the header links consistent with every other screen.
 */
Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    });
