<?php

namespace Database\Factories;

use App\Enums\TeamRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Client,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function ($user) {
            $team = Team::factory()->personal()->create([
                'name' => $user->name."'s Team",
            ]);

            $team->members()->attach($user, [
                'role' => TeamRole::Owner->value,
            ]);

            $user->switchTeam($team);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user acts on the client side of the platform.
     */
    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Client,
        ]);
    }

    /**
     * Indicate that the user is a student developer.
     */
    public function student(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Student,
        ]);
    }

    /**
     * Indicate that the user is a platform administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * Indicate that the account was approved by an administrator.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Approved,
        ]);
    }

    /**
     * Indicate that the account was deactivated and can no longer sign in.
     */
    public function deactivated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Deactivated,
        ]);
    }

    /**
     * Indicate that the account was created through Google and has no password.
     */
    public function fromGoogle(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
            'google_id' => (string) fake()->unique()->randomNumber(9, true),
            'avatar' => fake()->imageUrl(),
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
