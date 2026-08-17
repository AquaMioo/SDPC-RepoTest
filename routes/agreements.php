<?php

use App\Http\Controllers\Agreements\AgreementChangeRequestController;
use App\Http\Controllers\Agreements\AgreementController;
use App\Http\Controllers\Agreements\AgreementMilestoneController;
use App\Http\Controllers\Agreements\AgreementSignatureController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

/*
 * Agreements.
 *
 * The one part of the platform both roles reach on the same path. There is no
 * EnsureUserIsClient or EnsureUserIsStudent here on purpose: a contract has two
 * parties and either may open it, so the gate is AgreementPolicy, which knows
 * which side the signed-in user sits on. Adding role middleware would lock one
 * of the two signatories out of the document they are being asked to sign.
 *
 * Mounted on `{current_team}` like everything else, which for a student is
 * their own team and for a client is the business.
 */
Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('agreements', [AgreementController::class, 'index'])->name('agreements.index');
        Route::get('agreements/{agreement}', [AgreementController::class, 'show'])->name('agreements.show');
        Route::get('agreements/{agreement}/contract', [AgreementController::class, 'contract'])->name('agreements.contract');
        Route::patch('agreements/{agreement}', [AgreementController::class, 'update'])->name('agreements.update');

        Route::post('agreements/{agreement}/signatures', [AgreementSignatureController::class, 'store'])
            ->name('agreements.signatures.store');
        Route::post('agreements/{agreement}/change-requests', [AgreementChangeRequestController::class, 'store'])
            ->name('agreements.changes.store');

        Route::patch('agreements/{agreement}/milestones/{milestone}', [AgreementMilestoneController::class, 'update'])
            ->name('agreements.milestones.update');
    });
