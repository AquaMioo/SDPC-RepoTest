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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Read-only from the Client Module's perspective — the recruit grid and the
 * student profile screen consume it, the Student Module owns writing it.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $headline
 * @property string|null $biography
 * @property string|null $location
 * @property string|null $barangay
 * @property string|null $photo_path
 * @property int|null $school_id
 * @property int|null $course_id
 * @property int|null $year_level
 * @property Carbon|null $education_started_on
 * @property string|null $education_note
 * @property string|null $github_url
 * @property string|null $portfolio_url
 * @property bool $is_available
 * @property int|null $weekly_hours
 * @property string|null $availability_note
 * @property int|null $response_time_hours
 * @property int|null $hourly_rate
 * @property string $rating_average
 * @property int $completed_projects_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read School|null $school
 * @property-read Course|null $course
 * @property-read Collection<int, Skill> $skills
 * @property-read Collection<int, StudentPortfolioItem> $portfolioItems
 */
#[Fillable([
    'user_id', 'headline', 'biography', 'location', 'barangay', 'photo_path', 'school_id',
    'course_id', 'year_level', 'education_started_on', 'education_note',
    'github_url', 'portfolio_url', 'is_available', 'weekly_hours',
    'availability_note', 'response_time_hours', 'hourly_rate',
    'rating_average', 'completed_projects_count',
])]
class StudentProfile extends Model
{
    /** @use HasFactory<StudentProfileFactory> */
    use HasFactory;

    /**
     * The location as a client reads it.
     *
     * Narrowest part first, the way an address is spoken: "Towerville,
     * Barangay Muzon, San Jose Del Monte". Each piece is optional, so a
     * profile carrying only one of them still reads as a place rather than as
     * a string with stray commas in it.
     */
    public function displayLocation(): ?string
    {
        $parts = array_filter([
            $this->location,
            $this->barangay === null ? null : 'Barangay '.$this->barangay,
            $this->barangay === null ? null : 'San Jose Del Monte',
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

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
     * Get the work the student has already shipped, in their chosen order.
     *
     * @return HasMany<StudentPortfolioItem, $this>
     */
    public function portfolioItems(): HasMany
    {
        return $this->hasMany(StudentPortfolioItem::class)
            ->orderBy('position')
            ->orderByDesc('year');
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
            'education_started_on' => 'date',
        ];
    }
}
