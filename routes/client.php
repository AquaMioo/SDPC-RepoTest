<?php

use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Controllers\Client\ClientProfileController;
use App\Http\Controllers\Client\ProjectApplicationController;
use App\Http\Controllers\Client\ProjectController;
use App\Http\Controllers\Client\RecruitController;
use App\Http\Controllers\Client\StudentProfileController;
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

        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
        Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::get('projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
        Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

        Route::patch('projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
        Route::post('projects/{project}/duplicate', [ProjectController::class, 'duplicate'])->name('projects.duplicate');
        Route::patch('projects/{project}/intake', [ProjectController::class, 'toggleApplications'])->name('projects.intake.toggle');

        Route::get('projects/{project}/applicants', [ProjectApplicationController::class, 'index'])->name('projects.applicants.index');
        Route::post('projects/{project}/invitations', [ProjectApplicationController::class, 'invite'])->name('projects.invitations.store');
        Route::patch('applications/{application}', [ProjectApplicationController::class, 'update'])->name('applications.update');

        Route::get('recruit', RecruitController::class)->name('recruit.index');
        Route::get('students/{user}', StudentProfileController::class)->name('students.show');

        Route::get('business-profile', [ClientProfileController::class, 'edit'])->name('client-profile.edit');
        Route::patch('business-profile', [ClientProfileController::class, 'update'])->name('client-profile.update');
    });
