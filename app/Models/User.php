<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasTeams;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $email
 * @property UserRole $role
 * @property UserStatus $status
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $google_id
 * @property string|null $avatar
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $currentTeam
 * @property-read Collection<int, Team> $ownedTeams
 * @property-read Collection<int, Membership> $teamMemberships
 * @property-read Collection<int, Team> $teams
 * @property-read StudentProfile|null $studentProfile
 * @property-read Collection<int, Application> $applications
 * @property-read Collection<int, StudentCredential> $studentCredentials
 */
#[Fillable(['name', 'first_name', 'last_name', 'email', 'password', 'google_id', 'avatar', 'current_team_id'])]
#[Hidden(['password', 'google_id', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasTeams, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The model's default attribute values.
     *
     * Role and status are deliberately kept out of the fillable attributes so
     * they can never be mass assigned from user input. New accounts start with
     * the default role and status and must be changed explicitly — the sign up
     * form's role choice is applied in CreateNewUser, and status is only ever
     * moved by an administrator.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => UserRole::Student->value,
        'status' => UserStatus::Pending->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Determine if the user has any of the given roles.
     */
    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, strict: true);
    }

    /**
     * Determine if the user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin);
    }

    /**
     * Determine if the user acts on the client side of the platform.
     */
    public function isClient(): bool
    {
        return $this->hasRole(UserRole::Client);
    }

    /**
     * Determine if the user acts on the student side of the platform.
     */
    public function isStudent(): bool
    {
        return $this->hasRole(UserRole::Student);
    }

    /**
     * Determine if the account has been deactivated by an administrator.
     */
    public function isDeactivated(): bool
    {
        return ! $this->status->canAuthenticate();
    }

    /**
     * The credential documents this student has submitted.
     *
     * @return HasMany<StudentCredential, $this>
     */
    public function studentCredentials(): HasMany
    {
        return $this->hasMany(StudentCredential::class);
    }

    /**
     * The most recent credential submission, if any.
     *
     * @return HasOne<StudentCredential, $this>
     */
    public function latestStudentCredential(): HasOne
    {
        return $this->hasOne(StudentCredential::class)->latestOfMany();
    }

    /**
     * Determine if this account must submit a credential document.
     */
    public function requiresCredentialVerification(): bool
    {
        return $this->hasRole(UserRole::Student);
    }

    /**
     * Get the student profile, if the user is a student.
     *
     * @return HasOne<StudentProfile, $this>
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    /**
     * Get the applications the user has made to projects.
     *
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * Scope the query to users with the given role.
     *
     * @param  Builder<User>  $query
     */
    public function scopeRole(Builder $query, UserRole $role): void
    {
        $query->where('role', $role);
    }
}
