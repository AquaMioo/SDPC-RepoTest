<?php

use App\Http\Controllers\Messaging\ConversationController;
use App\Http\Controllers\Messaging\MeetingController;
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

        /*
         * Acting on one message. Editing and removing belong to its sender;
         * reacting is open to either side. All three check the message belongs
         * to the thread named in the URL, so an id borrowed from another
         * conversation 404s rather than resolving.
         */
        Route::patch('messages/{conversation}/{message}', [ConversationController::class, 'edit'])->name('messages.edit');
        Route::delete('messages/{conversation}/{message}', [ConversationController::class, 'remove'])->name('messages.remove');
        Route::post('messages/{conversation}/{message}/reactions', [ConversationController::class, 'react'])->name('messages.react');

        /*
         * Video meetings, on the same thread and behind the same participant
         * check. They 404 while config('agora.enabled') is false, so the
         * module is absent rather than offering a call that cannot be placed.
         *
         * The token is a separate call from starting the meeting because the
         * other side joins a call it did not create, and because a token
         * expires while the meeting it names may outlive it.
         */
        Route::post('messages/{conversation}/meetings', [MeetingController::class, 'store'])->name('meetings.store');
        Route::post('meetings/{meeting}/token', [MeetingController::class, 'token'])->name('meetings.token');
        Route::patch('meetings/{meeting}/end', [MeetingController::class, 'end'])->name('meetings.end');
    });
