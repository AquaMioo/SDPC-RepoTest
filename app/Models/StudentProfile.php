<?php

namespace App\Models;

use Database\Factories\StudentProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Read-only from the Client Module's perspective — the recruit grid and the
 * student profile screen consume it, the Student Module owns writing it.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $headline
 * @property string|null $biography
 * @property string|null $photo_path
 * @property int|null $school_id
 * @property int|null $course_id
 * @property int|null $year_level
 * @property string|null $github_url
 * @property string|null $portfolio_url
 * @property bool $is_available
 * @property int|null $hourly_rate
 * @property string $rating_average
 * @property int $completed_projects_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read School|null $school
 * @property-read Course|null $course
 * @property-read Collection<int, Skill> $skills
 */
#[Fillable([
    'user_id', 'headline', 'biography', 'photo_path', 'school_id', 'course_id',
    'year_level', 'github_url', 'portfolio_url', 'is_available', 'hourly_rate',
    'rating_average', 'completed_projects_count',
])]
class StudentProfile extends Model
{
    /** @use HasFactory<StudentProfileFactory> */
    use HasFactory;

    /**
     * Get the student the profile describes.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the student's school.
     *
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the student's course.
     *
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the skills the student claims.
     *
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class)->withTimestamps();
    }

    /**
     * Scope the query to students open to new work.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where('is_available', true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'rating_average' => 'decimal:2',
        ];
    }
}
