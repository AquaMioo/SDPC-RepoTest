<?php

namespace App\Models;

use Database\Factories\StudentPortfolioItemFactory;
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
 * One piece of work a student has already shipped.
 *
 * Written only by the student who owns the profile; read by every client
 * deciding whether to hire them. The skills pivot is what lets the matching
 * engine tell a claimed skill from a demonstrated one.
 *
 * @property int $id
 * @property int $student_profile_id
 * @property string $title
 * @property string|null $role
 * @property string|null $description
 * @property int|null $year
 * @property string|null $url
 * @property string|null $repository_url
 * @property bool $is_featured
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StudentProfile $studentProfile
 * @property-read Collection<int, Skill> $skills
 */
#[Fillable([
    'student_profile_id', 'title', 'role', 'description', 'year', 'url',
    'repository_url', 'is_featured', 'position',
])]
class StudentPortfolioItem extends Model
{
    /** @use HasFactory<StudentPortfolioItemFactory> */
    use HasFactory;

    /**
     * Get the profile the item belongs to.
     *
     * @return BelongsTo<StudentProfile, $this>
     */
    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    /**
     * Get the technologies the piece was built with.
     *
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'portfolio_item_skill', 'portfolio_item_id');
    }

    /**
     * Scope to the items the student chose to lead with.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function featured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
        ];
    }
}
