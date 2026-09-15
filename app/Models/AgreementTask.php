<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Database\Factories\AgreementTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One checklist item inside a phase of a signed agreement.
 *
 * Granular Task Completion: the student writes these and checks them off, the
 * client verifies them, and the share verified is the project's progress.
 *
 * @property int $id
 * @property int $agreement_milestone_id
 * @property int $position
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property string|null $proof_note
 * @property string|null $proof_url
 * @property string|null $proof_path
 * @property string|null $proof_name
 * @property Carbon|null $submitted_at
 * @property int|null $submitted_by
 * @property Carbon|null $verified_at
 * @property int|null $verified_by
 * @property string|null $review_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AgreementMilestone $milestone
 * @property-read User|null $submitter
 * @property-read User|null $verifier
 */
#[Fillable([
    'agreement_milestone_id', 'position', 'title', 'description', 'status',
    'proof_note', 'proof_url', 'proof_path', 'proof_name', 'submitted_at',
    'submitted_by', 'verified_at', 'verified_by', 'review_note',
])]
class AgreementTask extends Model
{
    /** @use HasFactory<AgreementTaskFactory> */
    use HasFactory;

    /**
     * The disk proof files are kept on.
     *
     * The public disk because the Railway volume is mounted there, but the
     * task-proofs directory is not linked into public/ — see config/filesystems
     * .php. The only way to a file is the proof route, which checks the reader.
     */
    public const PROOF_DISK = 'public';

    /**
     * Get the phase the task belongs to.
     *
     * @return BelongsTo<AgreementMilestone, $this>
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(AgreementMilestone::class, 'agreement_milestone_id');
    }

    /**
     * Get the student who checked it off.
     *
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the client team member who verified it.
     *
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Determine if the task belongs to the given agreement.
     *
     * Checked by every task route: the URL names the agreement, and a task id
     * from somebody else's contract must not resolve just because it exists.
     */
    public function belongsToAgreement(Agreement $agreement): bool
    {
        return $this->milestone->agreement_id === $agreement->id;
    }

    /**
     * Remove the stored proof file, if there is one.
     */
    public function forgetProofFile(): void
    {
        if ($this->proof_path !== null) {
            Storage::disk(self::PROOF_DISK)->delete($this->proof_path);
        }

        $this->forceFill(['proof_path' => null, 'proof_name' => null]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
