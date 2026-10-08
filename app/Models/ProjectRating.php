<?php

namespace App\Models;

use Database\Factories\ProjectRatingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A client's rating of one student on a completed build (owner, 2026-10-09).
 *
 * Written once, by CompleteProject, for the signer and each teammate.
 * Permanent: there is no route that edits or deletes one, and the model
 * refuses an update outright.
 *
 * @property int $id
 * @property int|null $agreement_id
 * @property int|null $project_id
 * @property int $student_id
 * @property int|null $client_team_id
 * @property int|null $rated_by
 * @property string $project_title
 * @property string $client_name
 * @property int $rating
 * @property string|null $feedback
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $student
 */
#[Fillable(['agreement_id', 'project_id', 'student_id', 'client_team_id', 'rated_by', 'project_title', 'client_name', 'rating', 'feedback'])]
class ProjectRating extends Model
{
    /** @use HasFactory<ProjectRatingFactory> */
    use HasFactory;

    /**
     * Ratings and feedback are permanent once sent.
     */
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('A project rating cannot be changed once sent.'));
    }

    /**
     * The student this rating is about.
     *
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Shape the rating for a profile's Feedback section.
     *
     * @return array{id: int, rating: int, feedback: string|null, clientName: string, projectTitle: string, ratedOn: string|null}
     */
    public function toReview(): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'feedback' => $this->feedback,
            'clientName' => $this->client_name,
            'projectTitle' => $this->project_title,
            'ratedOn' => $this->created_at?->format('j M Y'),
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }
}
