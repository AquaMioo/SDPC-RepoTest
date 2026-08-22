<?php

use App\Http\Controllers\Agreements\AgreementChangeRequestController;
use App\Http\Controllers\Agreements\AgreementController;
use App\Http\Controllers\Agreements\AgreementMilestoneController;
use App\Http\Controllers\Agreements\AgreementSignatureController;
use App\Http\Middleware\EnsureAccountIsNotMonitored;
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
        /*
         * An account under monitoring may read a contract it is party to and
         * keep reporting progress on one already signed, but may not agree to
         * new terms. Milestones are deliberately left open below: freezing
         * them would punish the other signatory for a decision that is not
         * theirs, and stall work that is already under way.
         */
        $trusted = EnsureAccountIsNotMonitored::class;

        Route::get('agreements', [AgreementController::class, 'index'])->name('agreements.index');
        Route::get('agreements/{agreement}', [AgreementController::class, 'show'])->name('agreements.show');
        Route::get('agreements/{agreement}/contract', [AgreementController::class, 'contract'])->name('agreements.contract');
        Route::patch('agreements/{agreement}', [AgreementController::class, 'update'])->middleware($trusted)->name('agreements.update');

        Route::post('agreements/{agreement}/signatures', [AgreementSignatureController::class, 'store'])
            ->middleware($trusted)
            ->name('agreements.signatures.store');
        Route::post('agreements/{agreement}/change-requests', [AgreementChangeRequestController::class, 'store'])
            ->middleware($trusted)
            ->name('agreements.changes.store');

        /*
         * Both models are declared on the controller in the order the URL
         * carries them, and the controller checks that the milestone belongs
         * to the agreement. Route-level scopeBindings() cannot do that job
         * here: it would also try to resolve `{agreement}` through
         * `{current_team}`, and for a student the current team is their own,
         * never the business the contract is with.
         */
        Route::patch('agreements/{agreement}/milestones/{milestone}', [AgreementMilestoneController::class, 'update'])
            ->name('agreements.milestones.update');
    });
