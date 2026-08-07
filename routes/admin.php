<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\Admin\AdminCredentialController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminIssueController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AdminOverviewController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Auth\GoogleAuthController;
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

        Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])
            ->middleware('throttle:10,1')
            ->name('google.redirect');
    });

    Route::middleware(['auth:'.$guard, 'role:'.UserRole::Admin->value])->group(function () {
        Route::get('dashboard', AdminDashboardController::class)->name('dashboard');
        Route::get('overview', AdminOverviewController::class)->name('overview');

        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/status', [AdminUserController::class, 'updateStatus'])
            ->name('users.status.update');

        Route::get('credentials', [AdminCredentialController::class, 'index'])
            ->name('credentials.index');
        Route::get('credentials/{credential}/document', [AdminCredentialController::class, 'document'])
            ->name('credentials.document');
        Route::patch('credentials/{credential}', [AdminCredentialController::class, 'update'])
            ->name('credentials.update');

        Route::get('content', AdminContentController::class)->name('content');
        Route::get('issues', AdminIssueController::class)->name('issues');
    });
});
