<?php

use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Controllers\Client\ClientProfileController;
use App\Http\Controllers\Client\ProjectApplicationController;
use App\Http\Controllers\Client\ProjectController;
use App\Http\Controllers\Client\RecruitController;
use App\Http\Controllers\Client\StudentProfileController;
use App\Http\Middleware\EnsureAccountIsVerified;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\EnsureUserIsClient;
use Illuminate\Support\Facades\Route;

/*
 * Client Module.
 *
 * Everything here is scoped to the acting team, which is the business. The
 * `current_team` segment is filled in automatically by SetTeamUrlDefaults, so
 * route() calls and the generated Wayfinder helpers take no team argument.
 */
Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class, EnsureUserIsClient::class])
    ->group(function () {
        Route::get('overview', ClientDashboardController::class)->name('client.dashboard');

        /*
         * Reading stays open to any client: looking around the module is how a
         * new business decides whether to finish signing up at all. Posting
         * work and hiring wait for `verified`, which is only granted once an
         * administrator has accepted the business permit.
         *
         * The gate is applied per route rather than as a group because order
         * matters here — Project binds on its slug, so `projects/create` has
         * to stay ahead of `projects/{project}` or it is read as a slug.
         */
        $verified = EnsureAccountIsVerified::class;

        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/create', [ProjectController::class, 'create'])->middleware($verified)->name('projects.create');
        Route::post('projects', [ProjectController::class, 'store'])->middleware($verified)->name('projects.store');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::get('projects/{project}/edit', [ProjectController::class, 'edit'])->middleware($verified)->name('projects.edit');
        Route::patch('projects/{project}', [ProjectController::class, 'update'])->middleware($verified)->name('projects.update');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->middleware($verified)->name('projects.destroy');

        Route::patch('projects/{project}/archive', [ProjectController::class, 'archive'])->middleware($verified)->name('projects.archive');
        Route::post('projects/{project}/duplicate', [ProjectController::class, 'duplicate'])->middleware($verified)->name('projects.duplicate');
        Route::patch('projects/{project}/intake', [ProjectController::class, 'toggleApplications'])->middleware($verified)->name('projects.intake.toggle');

        Route::get('projects/{project}/applicants', [ProjectApplicationController::class, 'index'])->name('projects.applicants.index');
        Route::post('projects/{project}/invitations', [ProjectApplicationController::class, 'invite'])->middleware($verified)->name('projects.invitations.store');
        Route::patch('applications/{application}', [ProjectApplicationController::class, 'update'])->middleware($verified)->name('applications.update');

        Route::get('recruit', RecruitController::class)->name('recruit.index');
        Route::get('students/{user}', StudentProfileController::class)->name('students.show');

        Route::get('business-profile', [ClientProfileController::class, 'edit'])->name('client-profile.edit');
        Route::patch('business-profile', [ClientProfileController::class, 'update'])->name('client-profile.update');
    });
