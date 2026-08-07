<?php

namespace App\Models;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Notifications\Client\ApplicationReceived;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property ApplicationStatus $status
 * @property ApplicationSource $source
 * @property string|null $cover_letter
 * @property int|null $proposed_rate
 * @property int|null $responded_by
 * @property Carbon|null $responded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read User $student
 * @property-read User|null $responder
 */
#[Fillable([
    'project_id', 'user_id', 'status', 'source', 'cover_letter',
    'proposed_rate', 'responded_by', 'responded_at',
])]
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        /*
         * Announced here rather than from a controller so the client is told
         * regardless of which module created the row — the Student Module
         * writes these too when a student applies.
         */
        static::created(function (Application $application) {
            if ($application->source !== ApplicationSource::Applied) {
                return;
            }

            Notification::send(
                $application->project->team->members,
                new ApplicationReceived($application),
            );
        });
    }

    /**
     * Get the project applied to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the student behind the application.
     *
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the client-side user who accepted or rejected the application.
     *
     * @return BelongsTo<User, $this>
     */
    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /**
     * Scope the query to applications in the given status.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function withStatus(Builder $query, ApplicationStatus $status): void
    {
        $query->where('status', $status);
    }

    /**
     * Scope the query to applications still awaiting a client decision.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function awaitingDecision(Builder $query): void
    {
        $query->whereIn('status', [
            ApplicationStatus::Pending->value,
            ApplicationStatus::Shortlisted->value,
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
            'status' => ApplicationStatus::class,
            'source' => ApplicationSource::class,
            'responded_at' => 'datetime',
        ];
    }
}
