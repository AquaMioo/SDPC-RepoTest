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
        Route::post('notifications/read-selected', [NotificationController::class, 'readSelected'])->name('notifications.read-selected');

        /*
         * Both destructive routes are declared before the {notification}
         * parameter below. `read-selected` would be caught by it otherwise —
         * the literal would read as an id and 404 on every submission.
         */
        Route::delete('notifications/read', [NotificationController::class, 'clear'])->name('notifications.clear');
        Route::delete('notifications', [NotificationController::class, 'destroy'])->name('notifications.destroy');

        Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    });
