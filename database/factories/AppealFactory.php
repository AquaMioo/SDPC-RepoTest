<?php

namespace Database\Factories;

use App\Enums\AppealStatus;
use App\Enums\UserStatus;
use App\Models\Appeal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appeal>
 */
class AppealFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['status' => UserStatus::Monitored]),
            'account_status' => UserStatus::Monitored,
            'body' => fake()->paragraph(),
            'status' => AppealStatus::Pending,
        ];
    }

    /**
     * An appeal from an account that can no longer sign in.
     */
    public function fromDeactivated(): static
    {
        return $this->state(fn (): array => [
            'user_id' => User::factory()->deactivated(),
            'account_status' => UserStatus::Deactivated,
        ]);
    }

    /**
     * An appeal an administrator has already decided.
     */
    public function decided(AppealStatus $status = AppealStatus::Denied): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'decision_note' => fake()->sentence(),
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }
}
