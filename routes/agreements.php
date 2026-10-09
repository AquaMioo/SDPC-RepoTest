<?php

use App\Http\Controllers\Agreements\AddendumController;
use App\Http\Controllers\Agreements\AddendumPaymentController;
use App\Http\Controllers\Agreements\AddendumServiceController;
use App\Http\Controllers\Agreements\AddendumSignatureController;
use App\Http\Controllers\Agreements\AgreementChangeRequestController;
use App\Http\Controllers\Agreements\AgreementController;
use App\Http\Controllers\Agreements\AgreementMilestoneController;
use App\Http\Controllers\Agreements\AgreementSignatureController;
use App\Http\Controllers\Agreements\AgreementTaskController;
use App\Http\Controllers\Agreements\DeadlineChangeRequestController;
use App\Http\Controllers\Agreements\MemorandumRequirementController;
use App\Http\Controllers\Agreements\PayMongoWebhookController;
use App\Http\Controllers\Agreements\PhaseScheduleController;
use App\Http\Controllers\Agreements\ProjectCompletionController;
use App\Http\Controllers\Agreements\ProjectManagementController;
use App\Http\Controllers\Agreements\ServiceDescriptionController;
use App\Http\Controllers\Agreements\SimulatedCheckoutController;
use App\Http\Middleware\EnsureAccountIsNotMonitored;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
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
        /* The same memorandum filled in, every addition in place, laid out to print and sign. */
        Route::get('agreements/{agreement}/printable', [AgreementController::class, 'printable'])->name('agreements.printable');
        Route::patch('agreements/{agreement}', [AgreementController::class, 'update'])->middleware($trusted)->name('agreements.update');

        /*
         * What either party adds to the memorandum: lines in its optional
         * sections, and the Section VII services that become the phases.
         * Open until somebody signs; each entry is its author's alone to
         * change. Both route models are declared on the controllers in URL
         * order, like milestones below, and checked against the agreement.
         */
        Route::post('agreements/{agreement}/requirements', [MemorandumRequirementController::class, 'store'])
            ->middleware($trusted)
            ->name('agreements.requirements.store');
        Route::patch('agreements/{agreement}/requirements/{requirement}', [MemorandumRequirementController::class, 'update'])
            ->middleware($trusted)
            ->name('agreements.requirements.update');
        Route::delete('agreements/{agreement}/requirements/{requirement}', [MemorandumRequirementController::class, 'destroy'])
            ->middleware($trusted)
            ->name('agreements.requirements.destroy');

        Route::post('agreements/{agreement}/services', [ServiceDescriptionController::class, 'store'])
            ->middleware($trusted)
            ->name('agreements.services.store');
        Route::patch('agreements/{agreement}/services/{service}', [ServiceDescriptionController::class, 'update'])
            ->middleware($trusted)
            ->name('agreements.services.update');
        Route::delete('agreements/{agreement}/services/{service}', [ServiceDescriptionController::class, 'destroy'])
            ->middleware($trusted)
            ->name('agreements.services.destroy');

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

        /*
         * The Payment & Project Extension Addendum: a second document beside
         * the memorandum, opened by the client's "Project extension" button
         * once the build is at least 80% done. Both parties fill Section II
         * and Section IV and sign; the client then pays its two milestones
         * through the gateway. AddendumPolicy decides; every controller checks
         * the addendum (and payment, service) belongs to the URL's agreement.
         */
        Route::post('agreements/{agreement}/addenda', [AddendumController::class, 'store'])
            ->middleware($trusted)
            ->name('agreements.addenda.store');
        Route::get('agreements/{agreement}/addenda/{addendum}', [AddendumController::class, 'show'])
            ->name('agreements.addenda.show');
        Route::patch('agreements/{agreement}/addenda/{addendum}', [AddendumController::class, 'update'])
            ->middleware($trusted)
            ->name('agreements.addenda.update');
        Route::delete('agreements/{agreement}/addenda/{addendum}', [AddendumController::class, 'destroy'])
            ->middleware($trusted)
            ->name('agreements.addenda.destroy');
        /* Filled in, signed or not, to print or save as a PDF. */
        Route::get('agreements/{agreement}/addenda/{addendum}/printable', [AddendumController::class, 'printable'])
            ->name('agreements.addenda.printable');
        /* The blank SDPC_Addendum.pdf, unsigned. */
        Route::get('agreements/{agreement}/addenda/{addendum}/template', [AddendumController::class, 'template'])
            ->name('agreements.addenda.template');
        /* The transaction record of its two milestones, to print or save. */
        Route::get('agreements/{agreement}/addenda/{addendum}/records', [AddendumController::class, 'records'])
            ->name('agreements.addenda.records');

        Route::post('agreements/{agreement}/addenda/{addendum}/services', [AddendumServiceController::class, 'store'])
            ->middleware($trusted)
            ->name('agreements.addenda.services.store');
        Route::patch('agreements/{agreement}/addenda/{addendum}/services/{service}', [AddendumServiceController::class, 'update'])
            ->middleware($trusted)
            ->name('agreements.addenda.services.update');
        Route::delete('agreements/{agreement}/addenda/{addendum}/services/{service}', [AddendumServiceController::class, 'destroy'])
            ->middleware($trusted)
            ->name('agreements.addenda.services.destroy');

        Route::post('agreements/{agreement}/addenda/{addendum}/signatures', [AddendumSignatureController::class, 'store'])
            ->middleware($trusted)
            ->name('agreements.addenda.signatures.store');

        Route::post('agreements/{agreement}/addenda/{addendum}/payments/{payment}/checkout', [AddendumPaymentController::class, 'checkout'])
            ->middleware(['throttle:12,1', $trusted])
            ->name('agreements.addenda.payments.checkout');
        /* PayMongo's success_url: back from the hosted checkout. */
        Route::get('agreements/{agreement}/addenda/{addendum}/payments/{payment}/paid', [AddendumPaymentController::class, 'paid'])
            ->name('agreements.addenda.payments.paid');
    });

/*
 * SDPC's test checkout, standing in for PayMongo's hosted page while no
 * PAYMONGO_SECRET_KEY is set (404 otherwise). Outside the team prefix like
 * PayMongo's own page: the payment row says which addendum it is.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('payments/simulated/{payment}', [SimulatedCheckoutController::class, 'show'])
        ->name('payments.simulated.show');
    Route::post('payments/simulated/{payment}', [SimulatedCheckoutController::class, 'store'])
        ->middleware('throttle:12,1')
        ->name('payments.simulated.store');
});

/*
 * PayMongo's server-to-server webhook (checkout_session.payment.paid). No
 * session and no CSRF token: the Paymongo-Signature header is the proof.
 */
Route::post('webhooks/paymongo', PayMongoWebhookController::class)
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->middleware('throttle:120,1')
    ->name('webhooks.paymongo');
