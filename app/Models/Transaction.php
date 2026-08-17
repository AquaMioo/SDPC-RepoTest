<?php

namespace App\Models;

use App\Enums\PaymentWallet;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line in the money ledger.
 *
 * Dormant while `config('billing.enabled')` is false, which is the default and
 * how the platform ships. The table, the model and the tests all exist so that
 * switching it on is a configuration change rather than a build.
 *
 * The platform records transfers; it does not make them. No gateway, no
 * credentials, no dependency.
 *
 * @property int $id
 * @property int $agreement_id
 * @property int|null $agreement_milestone_id
 * @property int $project_id
 * @property int $payer_team_id
 * @property int $payee_user_id
 * @property TransactionType $type
 * @property TransactionStatus $status
 * @property PaymentWallet $wallet
 * @property int $amount
 * @property string $description
 * @property string $reference
 * @property Carbon|null $benefit_period_start
 * @property Carbon|null $benefit_period_end
 * @property Carbon|null $posted_at
 * @property Carbon|null $settled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agreement $agreement
 * @property-read AgreementMilestone|null $milestone
 * @property-read Project $project
 * @property-read Team $payer
 * @property-read User $payee
 */
#[Fillable([
    'agreement_id', 'agreement_milestone_id', 'project_id', 'payer_team_id',
    'payee_user_id', 'type', 'status', 'wallet', 'amount', 'description',
    'reference', 'benefit_period_start', 'benefit_period_end', 'posted_at',
    'settled_at',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    /**
     * Get the agreement the payment belongs to.
     *
     * @return BelongsTo<Agreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /**
     * Get the milestone the payment covers, if it covers one.
     *
     * @return BelongsTo<AgreementMilestone, $this>
     */
    public function milestone(): BelongsTo
    {
        /* Named for what it is rather than for its column. */
        return $this->belongsTo(AgreementMilestone::class, 'agreement_milestone_id');
    }

    /**
     * Get the project the payment is for.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the business paying.
     *
     * @return BelongsTo<Team, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'payer_team_id');
    }

    /**
     * Get the student being paid.
     *
     * @return BelongsTo<User, $this>
     */
    public function payee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payee_user_id');
    }

    /**
     * Scope to the rows a given student may see.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function forPayee(Builder $query, User $student): void
    {
        $query->where('payee_user_id', $student->id);
    }

    /**
     * Scope to the rows a given business may see.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function forPayer(Builder $query, Team $team): void
    {
        $query->where('payer_team_id', $team->id);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'status' => TransactionStatus::class,
            'wallet' => PaymentWallet::class,
            'benefit_period_start' => 'date',
            'benefit_period_end' => 'date',
            'posted_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }
}
