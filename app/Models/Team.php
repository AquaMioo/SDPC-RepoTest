<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueTeamSlugs;
use App\Enums\TeamRole;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_personal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, TeamInvitation> $invitations
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 * @property-read ClientProfile|null $clientProfile
 * @property-read Testimonial|null $testimonial
 * @property-read Collection<int, Project> $projects
 */
#[Fillable(['name', 'slug', 'is_personal'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesUniqueTeamSlugs, HasFactory, SoftDeletes;

    /**
     * The most people one team may hold, counting the owner.
     *
     * A capstone group, not a company. Four is the whole team including
     * whoever formed it, so a leader invites three others.
     */
    public const MAX_MEMBERS = 4;

    /**
     * Slugs a team may never take.
     *
     * Team routes are mounted on a bare `{current_team}` prefix, so a slug is
     * indistinguishable from a top-level path. Without this list a business
     * named "Admin" would take the `admin` slug and shadow the admin portal.
     *
     * @var list<string>
     */
    public const RESERVED_SLUGS = [
        'admin',
        'api',
        'auth',
        'build',
        'credentials',
        'dashboard',
        'forgot-password',
        'invitations',
        'legal',
        'login',
        'logout',
        'register',
        'reset-password',
        'settings',
        'storage',
        'two-factor-challenge',
        'up',
        'user',
        'user-password',
        'verify-email',
        'well-known',
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            if (empty($team->slug)) {
                $team->slug = static::generateUniqueTeamSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueTeamSlug($team->name, $team->id);
            }
        });
    }

    /**
     * Get the team owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', TeamRole::Owner->value)
            ->first();
    }

    /**
     * Get all members of this team.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Every open vote to remove somebody from this team.
     *
     * @return HasMany<TeamRemovalVote, $this>
     */
    public function removalVotes(): HasMany
    {
        return $this->hasMany(TeamRemovalVote::class);
    }

    /**
     * Whether this team is still just one person.
     *
     * Distinct from is_personal, and the distinction matters. `is_personal`
     * records where the team came from — everybody is handed one at sign up —
     * and it never changes. This asks whether anybody else is actually in it.
     *
     * A student is not given a team and then asked to make a real one beside
     * it; the one they were given IS their team, and it becomes a group the
     * moment somebody accepts an invitation into it. So anything asking "is
     * there a group here" reads this, and only the rules about the team's
     * origin — you may not leave or delete the one you were handed — read
     * is_personal.
     */
    public function isSolo(): bool
    {
        return $this->members()->count() <= 1;
    }

    /**
     * Whether the team has reached MAX_MEMBERS.
     */
    public function isFull(): bool
    {
        return $this->members()->count() >= self::MAX_MEMBERS;
    }

    /**
     * How many more people the team could take, counting invitations already
     * sent and not yet answered.
     *
     * Pending invitations are counted because they are promises: three sent to
     * a team of two would seat five if everybody said yes, and the refusal
     * would land on whoever happened to accept last — somebody who did nothing
     * wrong and cannot see why they were turned away.
     */
    public function remainingSeats(): int
    {
        $taken = $this->members()->count()
            + $this->invitations()->whereNull('accepted_at')->count();

        return max(0, self::MAX_MEMBERS - $taken);
    }

    /**
     * Get all memberships for this team.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get all invitations for this team.
     *
     * @return HasMany<TeamInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * Get the business profile for this team.
     *
     * @return HasOne<ClientProfile, $this>
     */
    public function clientProfile(): HasOne
    {
        return $this->hasOne(ClientProfile::class);
    }

    /**
     * Get what this business says about working with a student team.
     *
     * @return HasOne<Testimonial, $this>
     */
    public function testimonial(): HasOne
    {
        return $this->hasOne(Testimonial::class);
    }

    /**
     * Get the projects this team has posted.
     *
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
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
