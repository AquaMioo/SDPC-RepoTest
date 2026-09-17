<?php

namespace App\Models;

use App\Enums\VerificationProvider;
use App\Enums\VerificationStatus;
use Database\Factories\StudentVerificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An automated answer about a student's enrolment.
 *
 * It only gates while a verifier is available — see
 * User::hasPassedStudentVerification(). Otherwise a verified row adds a badge
 * and gives a reviewer evidence, and that is the whole of its power.
 *
 * @property int $id
 * @property int $user_id
 * @property VerificationProvider $provider
 * @property VerificationStatus $status
 * @property Carbon|null $verified_at
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable([
    'user_id', 'provider', 'status', 'verified_at', 'failure_reason', 'payload',
])]
class StudentVerification extends Model
{
    /** @use HasFactory<StudentVerificationFactory> */
    use HasFactory;

    /**
     * Get the student the verification is about.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determine if this verification confirms the student.
     */
    public function isConfirmed(): bool
    {
        return $this->status === VerificationStatus::Verified;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => VerificationProvider::class,
            'status' => VerificationStatus::class,
            'payload' => 'array',
            'verified_at' => 'datetime',
        ];
    }
}
