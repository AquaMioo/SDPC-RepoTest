<?php

use App\Http\Controllers\Agreements\AgreementChangeRequestController;
use App\Http\Controllers\Agreements\AgreementController;
use App\Http\Controllers\Agreements\AgreementMilestoneController;
use App\Http\Controllers\Agreements\AgreementSignatureController;
use App\Http\Controllers\Agreements\AgreementTaskController;
use App\Http\Controllers\Agreements\DeadlineChangeRequestController;
use App\Http\Controllers\Agreements\PhaseScheduleController;
use App\Http\Controllers\Agreements\ProjectCompletionController;
use App\Http\Controllers\Agreements\ProjectManagementController;
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
        /* The school's blank Memorandum of Agreement, for printing and signing by hand. */
        Route::get('agreements/{agreement}/memorandum', [AgreementController::class, 'memorandum'])->name('agreements.memorandum');
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

        /*
         * Project Management — Granular Task Completion.
         *
         * One screen for both sides, so it lives here with the agreement it
         * tracks rather than in the client or student module. Who may do what
         * is AgreementPolicy's call (viewProgress, manageTasks, verifyTasks),
         * and all of it needs an active agreement.
         *
         * Like milestones above, none of these carry EnsureAccountIsNotMonitored:
         * a hold on one party must not freeze a build the other is part of.
         */
        Route::get('project-management', ProjectManagementController::class)->name('project-management');

        Route::patch('agreements/{agreement}/milestones/{milestone}/schedule', [PhaseScheduleController::class, 'update'])
            ->name('agreements.milestones.schedule');

        Route::post('agreements/{agreement}/milestones/{milestone}/tasks', [AgreementTaskController::class, 'store'])
            ->name('agreements.tasks.store');
        Route::put('agreements/{agreement}/milestones/{milestone}/tasks/order', [AgreementTaskController::class, 'reorder'])
            ->name('agreements.tasks.reorder');

        Route::patch('agreements/{agreement}/tasks/{task}', [AgreementTaskController::class, 'update'])
            ->name('agreements.tasks.update');
        Route::delete('agreements/{agreement}/tasks/{task}', [AgreementTaskController::class, 'destroy'])
            ->name('agreements.tasks.destroy');

        /* POST, not PATCH: a proof file comes with it, and PHP only parses multipart bodies on POST. */
        Route::post('agreements/{agreement}/tasks/{task}/submission', [AgreementTaskController::class, 'submit'])
            ->middleware('throttle:30,1')
            ->name('agreements.tasks.submit');
        Route::delete('agreements/{agreement}/tasks/{task}/submission', [AgreementTaskController::class, 'withdraw'])
            ->name('agreements.tasks.withdraw');

        Route::post('agreements/{agreement}/tasks/{task}/verification', [AgreementTaskController::class, 'verify'])
            ->name('agreements.tasks.verify');
        Route::post('agreements/{agreement}/tasks/{task}/return', [AgreementTaskController::class, 'sendBack'])
            ->name('agreements.tasks.send-back');

        Route::get('agreements/{agreement}/tasks/{task}/proof', [AgreementTaskController::class, 'proof'])
            ->name('agreements.tasks.proof');

        /*
         * Moving a deadline once it is set: the student side asks, the client
         * decides. See DeadlineChangeRequestController.
         */
        Route::post('agreements/{agreement}/tasks/{task}/deadline-requests', [DeadlineChangeRequestController::class, 'storeForTask'])
            ->name('agreements.tasks.deadline-requests.store');
        Route::post('agreements/{agreement}/milestones/{milestone}/deadline-requests', [DeadlineChangeRequestController::class, 'storeForFinalDeadline'])
            ->name('agreements.milestones.deadline-requests.store');
        Route::post('agreements/{agreement}/deadline-requests/{deadlineRequest}/approval', [DeadlineChangeRequestController::class, 'approve'])
            ->name('agreements.deadline-requests.approve');
        Route::post('agreements/{agreement}/deadline-requests/{deadlineRequest}/decline', [DeadlineChangeRequestController::class, 'decline'])
            ->name('agreements.deadline-requests.decline');
        Route::delete('agreements/{agreement}/deadline-requests/{deadlineRequest}', [DeadlineChangeRequestController::class, 'destroy'])
            ->name('agreements.deadline-requests.destroy');

        /* The client accepts the turnover. Final: see ProjectCompletionController. */
        Route::post('agreements/{agreement}/completion', [ProjectCompletionController::class, 'store'])
            ->name('agreements.completion.store');
    });
