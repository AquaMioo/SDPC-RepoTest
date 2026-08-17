<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $created_by
 * @property string $title
 * @property string $slug
 * @property string $description
 * @property string|null $objectives
 * @property string $category
 * @property string|null $industry
 * @property ProjectStatus $status
 * @property bool $applications_open
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read User|null $creator
 * @property-read Collection<int, Skill> $skills
 * @property-read Collection<int, ProjectAttachment> $attachments
 * @property-read Collection<int, Application> $applications
 * @property-read Collection<int, Recommendation> $recommendations
 */
#[Fillable([
    'team_id', 'created_by', 'title', 'slug', 'description', 'objectives',
    'category', 'industry',
    'status', 'applications_open', 'published_at',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Project $project) {
            if (empty($project->slug)) {
                $project->slug = static::generateUniqueSlug($project->title);
            }
        });
    }

    /**
     * Build a slug that stays unique across soft-deleted postings too.
     */
    public static function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'project';
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Get the team that owns the project.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who posted the project.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the skills required by the project.
     *
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class)
            ->withPivot('is_required')
            ->withTimestamps();
    }

    /**
     * Get the briefs and supporting files attached to the project.
     *
     * @return HasMany<ProjectAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class);
    }

    /**
     * Get every application and invitation against the project.
     *
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * Get the students working on the project.
     *
     * Accepted applications are the membership record; there is no separate
     * members table.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'applications')
            ->wherePivot('status', ApplicationStatus::Accepted->value)
            ->withTimestamps();
    }

    /**
     * Get the stored recommendation scores for the project.
     *
     * @return HasMany<Recommendation, $this>
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    /**
     * Get every contract drawn up against the project.
     *
     * One per accepted student, plus any superseded versions, so this is a
     * `hasMany` even where a single-student project usually holds one row.
     *
     * @return HasMany<Agreement, $this>
     */
    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class);
    }

    /**
     * Scope the query to a single owning team.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function forTeam(Builder $query, Team $team): void
    {
        $query->where('team_id', $team->id);
    }

    /**
     * Scope the query to postings students are allowed to see.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function publiclyVisible(Builder $query): void
    {
        $query->whereIn('status', collect(ProjectStatus::cases())
            ->filter(fn (ProjectStatus $status) => $status->isPubliclyVisible())
            ->map(fn (ProjectStatus $status) => $status->value));
    }

    /**
     * Scope the query to postings that are not finished yet.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function unfinished(Builder $query): void
    {
        $query->whereIn('status', collect(ProjectStatus::cases())
            ->filter(fn (ProjectStatus $status) => $status->isUnfinished())
            ->map(fn (ProjectStatus $status) => $status->value));
    }

    /**
     * Scope the query to postings still counted as active work.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', collect(ProjectStatus::active())
            ->map(fn (ProjectStatus $status) => $status->value));
    }

    /**
     * Determine if the project is currently taking applications.
     */
    public function isAcceptingApplications(): bool
    {
        if (! $this->applications_open || ! $this->status->acceptsApplications()) {
            return false;
        }

        return true;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'applications_open' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
