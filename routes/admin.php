<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminIssueController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AdminMonitoringController;
use App\Http\Controllers\Admin\AdminOverviewController;
use App\Http\Controllers\Admin\AdminPostingController;
use App\Http\Controllers\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

$guard = (string) config('fortify.guard');
$loginLimiter = config('fortify.limiters.login');

Route::prefix('admin')->name('admin.')->group(function () use ($guard, $loginLimiter) {
    Route::middleware(['guest:'.$guard])->group(function () use ($loginLimiter) {
        Route::get('login', [AdminLoginController::class, 'create'])->name('login');

        // Credentials are handled by Fortify itself. The role check lives in
        // App\Actions\Fortify\AuthenticateUser, which knows this request came
        // from the admin portal because of the "admin." route name prefix.
        Route::post('login', [AuthenticatedSessionController::class, 'store'])
            ->middleware($loginLimiter ? ['throttle:'.$loginLimiter] : [])
            ->name('login.store');

        /*
         * There is deliberately no Google button on this portal. Administrator
         * accounts are created by the developers, never self-served, so the
         * only way in is a password this team issued. App\Actions\Auth\
         * ResolveGoogleUser still refuses an administrator on the public
         * button, so removing the route closes the door rather than hiding it.
         */
    });

    Route::middleware(['auth:'.$guard, 'role:'.UserRole::Admin->value])->group(function () {
        Route::get('dashboard', AdminDashboardController::class)->name('dashboard');
        Route::get('overview', AdminOverviewController::class)->name('overview');

        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/status', [AdminUserController::class, 'updateStatus'])
            ->name('users.status.update');

        /*
         * The one review queue left. A posting sits in pending_review until it
         * is approved here, and the student board only lists open postings — so
         * skipping this decision means nothing a client writes ever reaches a
         * student.
         *
         * Credentials and business permits used to be reviewed here too. They
         * are not any more: a student is checked by the verification provider,
         * and a business is verified on registration.
         *
         * There is no index route: the queue is drawn by the dashboard
         * overview, which is where content management lives too.
         */
        Route::patch('postings/{posting}', [AdminPostingController::class, 'update'])
            ->name('postings.update');

        /* Same as postings: edited on the dashboard overview, saved here. */
        Route::put('content', [AdminContentController::class, 'update'])->name('content.update');

        Route::get('issues', [AdminIssueController::class, 'index'])->name('issues');
        Route::patch('issues/{issue}', [AdminIssueController::class, 'update'])->name('issues.update');

        /*
         * Monitoring. The roster of accounts currently held back, and the
         * appeals they have written about it — an account under monitoring
         * with nothing written still shows, or the status becomes one an
         * administrator sets and then loses track of.
         */
        Route::get('monitoring', [AdminMonitoringController::class, 'index'])->name('monitoring');
        Route::patch('monitoring/appeals/{appeal}', [AdminMonitoringController::class, 'decide'])
            ->name('appeals.update');
    });
});
