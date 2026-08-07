<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\StudentCredentialController;
use Illuminate\Support\Facades\Route;

$guard = (string) config('fortify.guard');

Route::middleware(['auth:'.$guard])->group(function () {
    Route::get('credentials', [StudentCredentialController::class, 'create'])->name('credentials.create');

    Route::post('credentials', [StudentCredentialController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('credentials.store');
});

Route::middleware(['guest:'.$guard, 'throttle:10,1'])->group(function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->name('google.redirect');

    // Google allows a single pre-registered redirect URI, so both portals come
    // back through this one callback. The originating portal is read from the
    // session inside the controller.
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('google.callback');
});
