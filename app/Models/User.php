<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasTeams;
use App\Enums\ApplicationStatus;
use App\Enums\CredentialStatus;
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
 * @property-read Collection<int, Agreement> $agreements
 * @property-read Collection<int, StudentVerification> $studentVerifications
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
     * Determine if the account has proved what it claims to be.
     *
     * Until this is true the account is read only: it may look around its
     * module but not post work, hire anyone, or apply for anything. A student
     * proves it with an accepted credential document, a client with an
     * accepted business permit. Administrators have nothing to prove.
     */
    public function isVerifiedForOperating(): bool
    {
        return match ($this->role) {
            UserRole::Admin => true,
            UserRole::Student => $this->latestStudentCredential?->status === CredentialStatus::Verified,
            UserRole::Client => $this->currentTeam?->clientProfile?->isVerified() ?? false,
        };
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
     * Get the contracts the student is a party to.
     *
     * @return HasMany<Agreement, $this>
     */
    public function agreements(): HasMany
    {
        /* Named for the role it plays, so the column has to be spelled out. */
        return $this->hasMany(Agreement::class, 'student_id');
    }

    /**
     * Get the third-party verifications held against this account.
     *
     * @return HasMany<StudentVerification, $this>
     */
    public function studentVerifications(): HasMany
    {
        return $this->hasMany(StudentVerification::class);
    }

    /**
     * Determine if the student may wear a verified badge.
     *
     * Two independent paths earn it and neither is required: an administrator
     * accepting the uploaded credential, or the optional third-party check
     * coming back verified. This is presentation only — what a student may
     * actually do is still decided by isVerifiedForOperating().
     */
    public function isVerifiedStudent(): bool
    {
        if ($this->latestStudentCredential?->status === CredentialStatus::Verified) {
            return true;
        }

        return $this->studentVerifications
            ->contains(fn (StudentVerification $verification): bool => $verification->isConfirmed());
    }

    /**
     * Determine if the student already has a build in hand.
     *
     * One student, one project — the platform exists to bridge a student to a
     * client, not to stack work on whoever answers first. Being accepted onto
     * a posting that is not finished is what counts as taken; once it is
     * completed, closed or archived they are free to take the next one.
     *
     * The mirror of the client's one-posting cap in ProjectPolicy::create().
     */
    public function holdsProjectInHand(): bool
    {
        return $this->applications()
            ->where('status', ApplicationStatus::Accepted)
            ->whereHas('project', fn (Builder $query) => $query->unfinished())
            ->exists();
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
