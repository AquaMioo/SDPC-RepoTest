<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\AdminBusinessController;
use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\Admin\AdminCredentialController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminIssueController;
use App\Http\Controllers\Admin\AdminLoginController;
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

        Route::get('businesses', [AdminBusinessController::class, 'index'])
            ->name('businesses.index');
        Route::get('businesses/{business}/permit', [AdminBusinessController::class, 'permit'])
            ->name('businesses.permit');
        Route::patch('businesses/{business}', [AdminBusinessController::class, 'update'])
            ->name('businesses.update');

        /*
         * The third review queue. A posting sits in pending_review until it is
         * approved here, and the student board only lists open postings — so
         * skipping this screen means nothing a client writes ever reaches a
         * student.
         */
        Route::get('postings', [AdminPostingController::class, 'index'])
            ->name('postings.index');
        Route::patch('postings/{posting}', [AdminPostingController::class, 'update'])
            ->name('postings.update');

        Route::get('credentials', [AdminCredentialController::class, 'index'])
            ->name('credentials.index');
        Route::get('credentials/{credential}/document', [AdminCredentialController::class, 'document'])
            ->name('credentials.document');
        Route::patch('credentials/{credential}', [AdminCredentialController::class, 'update'])
            ->name('credentials.update');

        Route::get('content', [AdminContentController::class, 'index'])->name('content');
        Route::put('content', [AdminContentController::class, 'update'])->name('content.update');
        Route::get('issues', AdminIssueController::class)->name('issues');
    });
});
