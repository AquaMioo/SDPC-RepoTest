<?php

namespace App\Models;

use App\Enums\AgreementParty;
use App\Enums\AgreementStatus;
use App\Enums\AgreementTemplate;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use Carbon\CarbonInterface;
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
 * @property AgreementTemplate $template
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
 * @property Carbon|null $completed_at
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
    'version', 'template', 'status', 'scope_summary', 'deliverables',
    'intellectual_property_terms', 'confidentiality_terms', 'academic_terms',
    'starts_on', 'ends_on', 'total_amount', 'activated_at', 'completed_at', 'superseded_by',
])]
class Agreement extends Model
{
    /** @use HasFactory<AgreementFactory> */
    use HasFactory, SoftDeletes;

    /**
     * New agreements are written as the school's Memorandum of Agreement.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'template' => 'memorandum',
    ];

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
     * Granular Task Completion: the tasks the client has verified over every
     * task in every phase. Nothing a student types in, and nothing a status
     * merely claims — a task checked off by the student but not yet verified
     * counts for nothing here, because only the client can say it is done.
     *
     * No tasks is 0%, not a division by zero: a checklist nobody has written
     * yet has nothing done on it.
     *
     * Every caller that reports progress — both dashboards, the agreement
     * screen, the messaging panel — goes through here or through
     * SummariseProgress, which counts the same way, so no two screens can
     * disagree about the figure.
     */
    public function progress(): int
    {
        $total = $this->taskCount();

        return $total === 0
            ? 0
            : (int) round($this->verifiedTaskCount() / $total * 100);
    }

    /**
     * Count every task across the agreement's phases.
     */
    public function taskCount(): int
    {
        $this->loadMissing('milestones.tasks');

        return $this->milestones->sum(fn (AgreementMilestone $milestone): int => $milestone->tasks->count());
    }

    /**
     * Count the tasks the client has verified.
     */
    public function verifiedTaskCount(): int
    {
        $this->loadMissing('milestones.tasks');

        return $this->milestones->sum(fn (AgreementMilestone $milestone): int => $milestone->tasks
            ->filter(fn (AgreementTask $task): bool => $task->status === TaskStatus::Verified)
            ->count());
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
     * Get the Turnover phase: the last one, whatever the client named it.
     *
     * Read from the loaded phases when they are there, so the screen can ask
     * per task without a query each.
     */
    public function turnoverPhase(): ?AgreementMilestone
    {
        return $this->milestones->sortBy('position')->last();
    }

    /**
     * Get the final deadline: the end of the Turnover phase.
     *
     * The working schedule's date, which only moves with the client's
     * approval. Every task deadline must fall on or before it, and it is the
     * day the project is due to end.
     */
    public function finalDeadline(): ?CarbonInterface
    {
        return $this->turnoverPhase()?->scheduledEndsOn();
    }

    /**
     * Get every ask to move one of this agreement's deadlines, newest first.
     *
     * @return HasMany<DeadlineChangeRequest, $this>
     */
    public function deadlineRequests(): HasMany
    {
        return $this->hasMany(DeadlineChangeRequest::class)->latest('id');
    }

    /**
     * Get the students working on this build: the signer and their team.
     *
     * Everyone on the team the signing student leads shares the build (see
     * AgreementPolicy::manageTasks), so this is who hears about it.
     *
     * @return Collection<int, User>
     */
    public function studentSide(): Collection
    {
        return User::query()
            ->whereKey($this->student_id)
            ->orWhereIn('id', Membership::query()
                ->select('user_id')
                ->whereIn('team_id', Membership::query()
                    ->select('team_id')
                    ->where('user_id', $this->student_id)
                    ->where('role', TeamRole::Owner->value)))
            ->get();
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
     * Scope to the agreements whose project the client completed.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function completed(Builder $query): void
    {
        $query->where('status', AgreementStatus::Completed);
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
            'template' => AgreementTemplate::class,
            'deliverables' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
