<?php

namespace App\Models;

use App\Enums\AddendumPaymentStatus;
use App\Enums\AddendumStatus;
use App\Enums\AgreementParty;
use App\Enums\TaskStatus;
use Database\Factories\AddendumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The Payment & Project Extension Addendum on a signed agreement.
 *
 * A second document beside the Memorandum of Agreement, never a new version
 * of it: the memorandum stays exactly as it was signed. The client asks for
 * one once the build is EXTENSION_THRESHOLD% done; both parties add the
 * extended services to Section II and set Section IV's target amount, and
 * both sign. Then the down payment (DOWN_PAYMENT_PERCENT%) is due through the
 * gateway, the services become Objective & Scope tasks, and once the student
 * has handed every one of them in the final balance is due. Its clearing
 * unlocks the tasks' files for the client.
 *
 * CA1 is the Contracting Agency, the student team lead; CA2 is the Customer
 * Agency, the client — the addendum's own wording, which is the reverse of the
 * memorandum's legend.
 *
 * @property int $id
 * @property int $agreement_id
 * @property int $sequence
 * @property string $reference
 * @property AddendumStatus $status
 * @property int|null $total_amount
 * @property int|null $requested_by
 * @property int|null $client_signed_by
 * @property string|null $client_signed_name
 * @property Carbon|null $client_signed_at
 * @property string|null $client_gcash_number
 * @property int|null $student_signed_by
 * @property string|null $student_signed_name
 * @property Carbon|null $student_signed_at
 * @property string|null $student_gcash_number
 * @property Carbon|null $executed_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agreement $agreement
 * @property-read User|null $requester
 * @property-read Collection<int, AddendumService> $services
 * @property-read Collection<int, AddendumPayment> $payments
 * @property-read Collection<int, AgreementTask> $tasks
 */
#[Fillable([
    'agreement_id', 'sequence', 'reference', 'status', 'total_amount', 'requested_by',
    'client_signed_by', 'client_signed_name', 'client_signed_at', 'client_gcash_number',
    'student_signed_by', 'student_signed_name', 'student_signed_at', 'student_gcash_number',
    'executed_at', 'completed_at', 'cancelled_at',
])]
#[Hidden(['client_gcash_number', 'student_gcash_number'])]
class Addendum extends Model
{
    /** @use HasFactory<AddendumFactory> */
    use HasFactory;

    /** The least Project Management progress, in percent, at which a client may ask. */
    public const EXTENSION_THRESHOLD = 80;

    /** Section IV's target amount, in whole pesos. */
    public const MIN_AMOUNT = 100;

    public const MAX_AMOUNT = 20000;

    /** Milestone 1's share of the target amount; Milestone 2 is the rest. */
    public const DOWN_PAYMENT_PERCENT = 30;

    protected $table = 'addenda';

    /**
     * Get the signed agreement this addendum extends.
     *
     * @return BelongsTo<Agreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /**
     * Get the client who asked for the extension.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get Section II's services, in the order they were added.
     *
     * @return HasMany<AddendumService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(AddendumService::class)->orderBy('id');
    }

    /**
     * Get Section IV's two milestones, down payment first.
     *
     * @return HasMany<AddendumPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(AddendumPayment::class)->orderBy('milestone');
    }

    /**
     * Get the Objective & Scope tasks the services became.
     *
     * @return HasMany<AgreementTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(AgreementTask::class);
    }

    /**
     * Get CA2, the Client Representative: the client account that posted the
     * project, or the business's owner account when the poster is gone — the
     * same account the memorandum names. Its GCash is the client's on record.
     */
    public function clientRepresentative(): ?User
    {
        $project = $this->agreement->project;
        $owner = $project->team->owner();

        return $project->creator ?? ($owner instanceof User ? $owner : null);
    }

    /**
     * Get CA1, the Student Team Lead: the student who signed the memorandum.
     */
    public function studentRepresentative(): User
    {
        return $this->agreement->student;
    }

    /**
     * Determine if the given party has signed.
     */
    public function isSignedBy(AgreementParty $party): bool
    {
        return $party === AgreementParty::Client
            ? $this->client_signed_at !== null
            : $this->student_signed_at !== null;
    }

    /**
     * Determine if anybody has signed yet, which is what closes the terms.
     */
    public function hasAnySignature(): bool
    {
        return $this->client_signed_at !== null || $this->student_signed_at !== null;
    }

    /**
     * Get Milestone 1 (the down payment) or Milestone 2 (the final balance).
     */
    public function payment(int $milestone): ?AddendumPayment
    {
        return $this->payments->firstWhere('milestone', $milestone);
    }

    /**
     * Split the target amount into the two milestones, in centavos.
     *
     * @return array{1: int, 2: int}
     */
    public function milestoneAmounts(): array
    {
        $total = (int) $this->total_amount * 100;
        $down = intdiv($total * self::DOWN_PAYMENT_PERCENT, 100);

        return [1 => $down, 2 => $total - $down];
    }

    /**
     * Determine if the student has handed in all of the extended work.
     *
     * Every task the services became is submitted or verified: nothing is
     * still open. That is "the student completes the work", which makes the
     * final balance due.
     */
    public function isWorkHandedIn(): bool
    {
        return $this->tasks->isNotEmpty()
            && $this->tasks->every(fn (AgreementTask $task): bool => $task->status !== TaskStatus::Open);
    }

    /**
     * Determine if the given milestone may be paid now.
     *
     * The down payment as soon as both have signed; the final balance once the
     * down payment cleared and the work is handed in.
     */
    public function isPayable(int $milestone): bool
    {
        $payment = $this->payment($milestone);

        if ($this->status !== AddendumStatus::Active || $payment === null || $payment->status === AddendumPaymentStatus::Paid) {
            return false;
        }

        if ($milestone === 1) {
            return true;
        }

        return $this->payment(1)?->status === AddendumPaymentStatus::Paid && $this->isWorkHandedIn();
    }

    /**
     * Scope to the addenda still being drafted, signed or paid.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', AddendumStatus::open());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AddendumStatus::class,
            'client_signed_at' => 'datetime',
            'student_signed_at' => 'datetime',
            'client_gcash_number' => 'encrypted',
            'student_gcash_number' => 'encrypted',
            'executed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
