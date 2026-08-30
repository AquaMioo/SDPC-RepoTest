<?php

namespace App\Models;

use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $city
 * @property string|null $domain
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, StudentProfile> $studentProfiles
 */
#[Fillable(['name', 'slug', 'city', 'domain'])]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (School $school) {
            if (empty($school->slug)) {
                $school->slug = Str::slug($school->name);
            }
        });
    }

    /**
     * Find the school that issues addresses on the given email domain.
     *
     * EXACT equality, never a suffix or substring match. `endsWith('.edu.ph')`
     * would admit anybody who can register any .edu.ph domain, and
     * str_contains would admit "sti.edu.ph.attacker.com" — the whole strength
     * of this check is that the domain is one somebody put on the list on
     * purpose.
     *
     * Compared lowercased on both sides: domains are case-insensitive, and a
     * seeder or an administrator will eventually type one with a capital.
     */
    public static function forEmailDomain(string $domain): ?self
    {
        $domain = mb_strtolower(trim($domain));

        return $domain === ''
            ? null
            : self::query()->whereRaw('LOWER(domain) = ?', [$domain])->first();
    }

    /**
     * Get every school name, alphabetically.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return self::query()->orderBy('name')->pluck('name')->all();
    }

    /**
     * Get the student profiles attached to this school.
     *
     * @return HasMany<StudentProfile, $this>
     */
    public function studentProfiles(): HasMany
    {
        return $this->hasMany(StudentProfile::class);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
