<?php

namespace App\Http\Controllers\Billing;

use App\Enums\PaymentWallet;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Transaction screen — "My transactions" for a student, the billing history
 * for a client.
 *
 * One controller for both because the ledger is one table read from two sides;
 * which rows a person sees is decided by whether they are the payee or the
 * payer, never by a role check on the screen.
 *
 * Unreachable while billing is switched off — see EnsureBillingIsEnabled.
 */
class TransactionController extends Controller
{
    /**
     * List the transactions the signed-in user is a party to.
     */
    public function index(Request $request, Team $currentTeam): Response
    {
        $user = $request->user();

        $transactions = $this->ledgerFor($user, $currentTeam)
            ->with(['project', 'milestone'])
            ->when($request->filled('wallet'), fn (Builder $query) => $query
                ->where('wallet', $request->string('wallet')->toString()))
            ->when($request->filled('type'), fn (Builder $query) => $query
                ->where('type', $request->string('type')->toString()))
            ->when($request->filled('search'), fn (Builder $query) => $query
                ->where('description', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderByDesc($request->string('sort')->toString() === 'amount' ? 'amount' : 'created_at')
            ->get();

        return Inertia::render('billing/transactions', [
            'transactions' => $transactions
                ->map(fn (Transaction $transaction): array => [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'date' => $transaction->created_at?->format('j M Y'),
                    'description' => $transaction->description,
                    'wallet' => $transaction->wallet->label(),
                    'benefitPeriod' => $this->benefitPeriod($transaction),
                    'type' => $transaction->type->label(),
                    'amount' => $transaction->amount,
                    'status' => $transaction->status->value,
                    'statusLabel' => $transaction->status->label(),
                    'statusVariant' => $transaction->status->tagVariant(),
                ])
                ->values()
                ->all(),
            'totals' => [
                /* Only settled money is money. Anything else is a promise. */
                'settled' => (int) $transactions
                    ->filter(fn (Transaction $transaction): bool => $transaction->status->countsAsEarned())
                    ->sum('amount'),
                'outstanding' => (int) $transactions
                    ->reject(fn (Transaction $transaction): bool => $transaction->status->countsAsEarned())
                    ->sum('amount'),
            ],
            'filters' => [
                'wallets' => collect(PaymentWallet::cases())
                    ->map(fn (PaymentWallet $wallet): array => [
                        'value' => $wallet->value,
                        'label' => $wallet->label(),
                    ])
                    ->all(),
                'types' => collect(TransactionType::cases())
                    ->map(fn (TransactionType $type): array => [
                        'value' => $type->value,
                        'label' => $type->label(),
                    ])
                    ->all(),
                'statuses' => collect(TransactionStatus::cases())
                    ->map(fn (TransactionStatus $status): array => [
                        'value' => $status->value,
                        'label' => $status->label(),
                    ])
                    ->all(),
            ],
            'isStudent' => $user->isStudent(),
        ]);
    }

    /**
     * Get the ledger rows this user is entitled to read.
     *
     * @return Builder<Transaction>
     */
    protected function ledgerFor(User $user, Team $currentTeam): Builder
    {
        return $user->isStudent()
            ? Transaction::query()->forPayee($user)
            : Transaction::query()->forPayer($currentTeam);
    }

    /**
     * Render the window of work a payment covers.
     */
    protected function benefitPeriod(Transaction $transaction): ?string
    {
        if ($transaction->benefit_period_start === null || $transaction->benefit_period_end === null) {
            return null;
        }

        return $transaction->benefit_period_start->format('j M')
            .' – '.$transaction->benefit_period_end->format('j M Y');
    }
}
