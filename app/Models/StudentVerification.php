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
 * A third party's answer about a student's enrolment.
 *
 * Optional throughout. Nothing reads this to decide whether a student may
 * apply, message or sign — App\Http\Middleware\EnsureAccountIsVerified still
 * answers to the administrator-reviewed credential alone. A verified row adds
 * a badge and gives a reviewer evidence, and that is the whole of its power.
 *
 * @property int $id
 * @property int $user_id
 * @property VerificationProvider $provider
 * @property VerificationStatus $status
 * @property string|null $external_id
 * @property string|null $redirect_url
 * @property Carbon|null $verified_at
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable([
    'user_id', 'provider', 'status', 'external_id', 'redirect_url',
    'verified_at', 'failure_reason', 'payload',
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
