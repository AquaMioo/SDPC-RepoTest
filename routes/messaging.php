<?php

use App\Http\Controllers\Messaging\ConversationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

/*
 * Messaging.
 *
 * The one part of the platform both modules share, so it carries no role
 * middleware: a thread's own participant check is the gate, and it already
 * knows a client from a student.
 */
Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('messages', [ConversationController::class, 'index'])->name('messages.index');
        Route::post('messages', [ConversationController::class, 'store'])->name('messages.store');
        Route::get('messages/{conversation}', [ConversationController::class, 'index'])->name('messages.show');
        Route::post('messages/{conversation}', [ConversationController::class, 'send'])->name('messages.send');
    });
