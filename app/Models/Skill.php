<?php

namespace App\Models;

use App\Enums\SkillType;
use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property SkillType $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Project> $projects
 * @property-read Collection<int, StudentProfile> $studentProfiles
 */
#[Fillable(['name', 'slug', 'type'])]
class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Skill $skill) {
            if (empty($skill->slug)) {
                $skill->slug = Str::slug($skill->name);
            }
        });
    }

    /**
     * Resolve a skill by name, creating it if the tagger saw it first.
     */
    public static function findOrCreateByName(string $name, SkillType $type = SkillType::General): self
    {
        return static::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => trim($name), 'type' => $type],
        );
    }

    /**
     * Resolve a list of typed-in names to skill ids, minting what is new.
     *
     * Everything that syncs a skill pivot — a posting's requirements, a
     * student's claimed skills, the technologies on a portfolio piece — goes
     * through here, so "laravel" and "Laravel" cannot become two rows in one
     * place and one row in another.
     *
     * @param  array<int, mixed>  $names
     * @return list<int>
     */
    public static function idsForNames(array $names, SkillType $type = SkillType::General): array
    {
        return array_values(collect($names)
            ->filter(fn (mixed $name): bool => is_string($name))
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique()
            ->map(fn (string $name): int => static::findOrCreateByName($name, $type)->id)
            ->all());
    }

    /**
     * Get the projects requiring this skill.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withPivot('is_required')
            ->withTimestamps();
    }

    /**
     * Get the student profiles claiming this skill.
     *
     * @return BelongsToMany<StudentProfile, $this>
     */
    public function studentProfiles(): BelongsToMany
    {
        return $this->belongsToMany(StudentProfile::class)->withTimestamps();
    }

    /**
     * Scope the query to a single skill type.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ofType(Builder $query, SkillType $type): void
    {
        $query->where('type', $type);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SkillType::class,
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
