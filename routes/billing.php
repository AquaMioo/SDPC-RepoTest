<?php

use App\Http\Controllers\Billing\TransactionController;
use App\Http\Middleware\EnsureBillingIsEnabled;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

/*
 * Billing.
 *
 * Registered on every boot but sealed behind EnsureBillingIsEnabled, which
 * 404s while `config('billing.enabled')` is false — the default. The routes
 * exist so the feature is one environment variable away from working, not so
 * anybody can reach them today.
 *
 * Like agreements, this is shared ground: a student reads the ledger as the
 * payee and a client reads it as the payer, and the controller scopes the rows
 * rather than a role middleware picking a side.
 */
Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class, EnsureBillingIsEnabled::class])
    ->group(function () {
        Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    });
