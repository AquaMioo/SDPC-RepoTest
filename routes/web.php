<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use App\Models\Team;
use Illuminate\Support\Facades\Route;

/*
 * The client module mounts every one of its routes on a bare `{current_team}`
 * prefix, which matches any single path segment — including "admin". Two things
 * keep that from swallowing the rest of the application:
 *
 *   1. This pattern, which stops {current_team} matching a reserved segment.
 *   2. Registration order: the fixed-path route files are required first, so
 *      they win even if the pattern is ever loosened.
 *
 * Team::RESERVED_SLUGS is also enforced at slug generation, so no team can hold
 * one of these names in the first place.
 */
Route::pattern('current_team', '(?!(?:'.implode('|', array_map(
    fn (string $slug): string => preg_quote($slug, '/'),
    Team::RESERVED_SLUGS,
)).')$)[A-Za-z0-9._-]+');

/*
 * The landing page is deliberately public: it is the platform's shop window and
 * must render for signed-out visitors.
 */
Route::get('/', HomeController::class)->name('home');

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/settings.php';

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/messaging.php';
require __DIR__.'/student.php';
require __DIR__.'/client.php';
