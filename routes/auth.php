<?php

use App\Http\Controllers\Auth\AccountAppealController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\StudentCredentialController;
use Illuminate\Support\Facades\Route;

$guard = (string) config('fortify.guard');

Route::middleware(['auth:'.$guard])->group(function () {
    Route::get('credentials', [StudentCredentialController::class, 'create'])->name('credentials.create');

    Route::post('credentials', [StudentCredentialController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('credentials.store');
});

/*
 * Registration.
 *
 * These two names, `register` and `register.store`, are Fortify's — the
 * feature is switched off in config/fortify.php so this controller can own
 * them, because Fortify has no seam between validating a sign up and creating
 * the account. Every existing route() call and Wayfinder helper keeps
 * resolving; what changed is that a code now stands between the two.
 *
 * The admin portal registers nobody and is untouched: administrator accounts
 * are created by the developers.
 */
Route::middleware(['guest:'.$guard])->group(function () {
    Route::get('register', [RegistrationController::class, 'create'])->name('register');

    Route::post('register', [RegistrationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('register.store');

    Route::get('register/verify', [RegistrationController::class, 'verify'])
        ->name('register.verify');

    /*
     * Throttled harder than the form itself: this is the endpoint somebody
     * would sit on guessing six digits. The per-code attempt budget in
     * OneTimePasswordService is the real limit; this stops it being spent by a
     * script in under a second.
     */
    Route::post('register/verify', [RegistrationController::class, 'confirm'])
        ->middleware('throttle:10,1')
        ->name('register.verify.store');

    Route::post('register/verify/resend', [RegistrationController::class, 'resend'])
        ->middleware('throttle:5,1')
        ->name('register.verify.resend');

    Route::delete('register/verify', [RegistrationController::class, 'cancel'])
        ->name('register.verify.cancel');
});

/*
 * Appeals from accounts that cannot sign in.
 *
 * Open to guests by necessity: a deactivated account is locked out of the
 * appeal that lives in settings, and it is the account most likely to want
 * one. Identity is proved with an emailed code rather than a password, because
 * an account created through Google never had a password to be asked for.
 */
Route::middleware(['guest:'.$guard])->group(function () {
    Route::get('appeal', [AccountAppealController::class, 'create'])->name('appeal');

    Route::post('appeal/code', [AccountAppealController::class, 'sendCode'])
        ->middleware('throttle:5,1')
        ->name('appeal.code');

    Route::post('appeal/code/resend', [AccountAppealController::class, 'resend'])
        ->middleware('throttle:5,1')
        ->name('appeal.code.resend');

    /*
     * Named `submit` rather than `store` so it cannot collide with the signed
     * in appeal at profile.appeal.store — duplicate route names do not error,
     * the second simply wins every route() lookup.
     */
    Route::post('appeal', [AccountAppealController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('appeal.submit');
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
