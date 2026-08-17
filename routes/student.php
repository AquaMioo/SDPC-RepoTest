<?php

use App\Http\Controllers\Student\ProjectBoardController;
use App\Http\Controllers\Student\WorkflowController;
use App\Http\Middleware\EnsureAccountIsVerified;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\EnsureUserIsStudent;
use Illuminate\Support\Facades\Route;

/*
 * Student Module.
 *
 * Mounted on the same `{current_team}` prefix as the client module, but on
 * paths that module does not use — the two groups are separated by role
 * middleware, not by URL space, so an overlapping path would be resolved by
 * registration order and silently 403 whichever role lost.
 */
Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class, EnsureUserIsStudent::class])
    ->group(function () {
        /*
         * Browsing is open to any student, the same way the client module lets
         * an unverified business look around. Applying is what waits for an
         * administrator to accept the credential.
         */
        $verified = EnsureAccountIsVerified::class;

        Route::get('find-clients', [ProjectBoardController::class, 'index'])->name('student.board.index');
        Route::get('find-clients/{project}', [ProjectBoardController::class, 'show'])->name('student.board.show');
        Route::post('find-clients/{project}/apply', [ProjectBoardController::class, 'apply'])
            ->middleware($verified)
            ->name('student.board.apply');

        Route::get('workflow', WorkflowController::class)->name('student.workflow');
        Route::delete('my-applications/{application}', [ProjectBoardController::class, 'withdraw'])
            ->name('student.applications.withdraw');
    });
