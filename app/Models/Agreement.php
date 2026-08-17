<?php

namespace App\Models;

use App\Enums\AgreementParty;
use App\Enums\AgreementStatus;
use Database\Factories\AgreementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * The negotiated contract for one project and one student.
 *
 * Everything a posting stopped carrying — scope, money, dates — is agreed here
 * after acceptance and signed by both sides. Signing is what starts the work:
 * the project only moves into progress when the second signature lands, which
 * is the platform's reading of "the Terms and Agreements Form is displayed for
 * confirmation before collaboration begins".
 *
 * @property int $id
 * @property int $project_id
 * @property int $application_id
 * @property int $team_id
 * @property int $student_id
 * @property string $reference
 * @property int $version
 * @property AgreementStatus $status
 * @property string|null $scope_summary
 * @property list<string>|null $deliverables
 * @property string|null $intellectual_property_terms
 * @property string|null $confidentiality_terms
 * @property string|null $academic_terms
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property int $total_amount
 * @property Carbon|null $activated_at
 * @property int|null $superseded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Project $project
 * @property-read Application $application
 * @property-read Team $team
 * @property-read User $student
 * @property-read Agreement|null $successor
 * @property-read Collection<int, AgreementMilestone> $milestones
 * @property-read Collection<int, AgreementSignature> $signatures
 * @property-read Collection<int, Transaction> $transactions
 */
#[Fillable([
    'project_id', 'application_id', 'team_id', 'student_id', 'reference',
    'version', 'status', 'scope_summary', 'deliverables',
    'intellectual_property_terms', 'confidentiality_terms', 'academic_terms',
    'starts_on', 'ends_on', 'total_amount', 'activated_at', 'superseded_by',
])]
class Agreement extends Model
{
    /** @use HasFactory<AgreementFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the project the agreement covers.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the accepted application that produced this agreement.
     *
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the business on the client side.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the student on the other side.
     *
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        /* Named for the role it plays, so the column has to be spelled out. */
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the version that replaced this one, if a change request was accepted.
     *
     * @return BelongsTo<Agreement, $this>
     */
    public function successor(): BelongsTo
    {
        return $this->belongsTo(Agreement::class, 'superseded_by');
    }

    /**
     * Get the agreed pieces of work, in the order they were agreed.
     *
     * @return HasMany<AgreementMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(AgreementMilestone::class)->orderBy('position');
    }

    /**
     * Get the signatures on the contract log.
     *
     * @return HasMany<AgreementSignature, $this>
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(AgreementSignature::class);
    }

    /**
     * Get the money recorded against this agreement.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Determine if the given party has signed.
     */
    public function isSignedBy(AgreementParty $party): bool
    {
        return $this->signatures->contains(
            fn (AgreementSignature $signature): bool => $signature->party === $party,
        );
    }

    /**
     * Determine if both sides have signed.
     */
    public function isFullySigned(): bool
    {
        return $this->isSignedBy(AgreementParty::Client)
            && $this->isSignedBy(AgreementParty::Student);
    }

    /**
     * Get how far the agreed work has moved, as a percentage.
     *
     * Averaged across the milestones because they are equal steps through the
     * build, not weighted by price — a cheap turnover phase is not less done
     * than an expensive one.
     */
    public function progress(): int
    {
        $milestones = $this->milestones;

        if ($milestones->isEmpty()) {
            return 0;
        }

        return (int) round(
            $milestones->sum(fn (AgreementMilestone $milestone): int => $milestone->status->progress())
            / $milestones->count(),
        );
    }

    /**
     * Recalculate the denormalised total from the milestones.
     */
    public function syncTotalAmount(): void
    {
        $this->forceFill([
            'total_amount' => (int) $this->milestones()->sum('amount'),
        ])->save();
    }

    /**
     * Scope to the agreements that govern work in flight.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', AgreementStatus::Active);
    }

    /**
     * Scope to the agreements neither side has walked away from.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function standing(Builder $query): void
    {
        $query->whereIn('status', [
            AgreementStatus::Draft,
            AgreementStatus::AwaitingSignatures,
            AgreementStatus::Active,
        ]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AgreementStatus::class,
            'deliverables' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'activated_at' => 'datetime',
        ];
    }
}
