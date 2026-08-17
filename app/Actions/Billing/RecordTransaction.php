<?php

namespace App\Actions\Billing;

use App\Enums\PaymentWallet;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\AgreementMilestone;
use App\Models\Transaction;
use Illuminate\Support\Str;

/**
 * The single door into the money ledger.
 *
 * Everything that would write a transaction goes through here, which is what
 * makes switching billing off a one-line change instead of a hunt: with
 * `config('billing.enabled')` false this returns null and writes nothing, so no
 * caller has to know whether the feature is live.
 */
class RecordTransaction
{
    /**
     * Record the payment owed for an approved milestone.
     *
     * Pending, not paid — the platform does not move money and will not claim
     * that it has. The two parties settle it themselves and mark the row.
     */
    public function forMilestone(AgreementMilestone $milestone): ?Transaction
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $agreement = $milestone->agreement;

        /* An approval that ran twice must not bill the client twice. */
        $existing = Transaction::query()
            ->where('agreement_milestone_id', $milestone->id)
            ->where('type', TransactionType::Milestone)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Transaction::query()->create([
            'agreement_id' => $agreement->id,
            'agreement_milestone_id' => $milestone->id,
            'project_id' => $agreement->project_id,
            'payer_team_id' => $agreement->team_id,
            'payee_user_id' => $agreement->student_id,
            'type' => TransactionType::Milestone,
            'status' => TransactionStatus::Pending,
            'wallet' => PaymentWallet::Unset,
            'amount' => $milestone->amount,
            'description' => $agreement->project->title.' · '.$milestone->title,
            'reference' => $this->nextReference(),
            'benefit_period_start' => $milestone->starts_on,
            'benefit_period_end' => $milestone->ends_on,
        ]);
    }

    /**
     * Determine whether the ledger is accepting writes at all.
     */
    protected function isEnabled(): bool
    {
        return (bool) config('billing.enabled');
    }

    /**
     * Build a reference the two parties can quote to each other.
     */
    protected function nextReference(): string
    {
        return 'TXN-'.Str::upper(Str::random(10));
    }
}
