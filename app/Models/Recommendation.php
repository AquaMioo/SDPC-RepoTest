<?php

namespace App\Models;

use Database\Factories\RecommendationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Scaffolding for the AI module. Nothing in the Client Module writes scores;
 * the recruit screen reads them when they exist and falls back to a
 * deterministic ordering when they do not.
 *
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property string $score
 * @property int $compatibility_percentage
 * @property array<string, mixed>|null $reason
 * @property string|null $generated_by
 * @property Carbon|null $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read User $student
 */
#[Fillable([
    'project_id', 'user_id', 'score', 'compatibility_percentage',
    'reason', 'generated_by', 'generated_at',
])]
class Recommendation extends Model
{
    /** @use HasFactory<RecommendationFactory> */
    use HasFactory;

    /**
     * Get the project the score was generated for.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the recommended student.
     *
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'decimal:4',
            'reason' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
