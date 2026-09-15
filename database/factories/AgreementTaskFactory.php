<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\AgreementMilestone;
use App\Models\AgreementTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgreementTask>
 */
class AgreementTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agreement_milestone_id' => AgreementMilestone::factory(),
            'position' => 1,
            'title' => fake()->randomElement([
                'Scope & requirements sign-off',
                'UI wireframes',
                'Database schema',
                'Authentication & roles',
                'In-app messaging',
            ]),
            'description' => null,
            'status' => TaskStatus::Open,
        ];
    }

    /**
     * Indicate that the student checked the task off with proof.
     */
    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => TaskStatus::Submitted,
            'proof_note' => fake()->sentence(),
            'submitted_at' => now(),
        ]);
    }

    /**
     * Indicate that the client verified the task.
     */
    public function verified(): static
    {
        return $this->state(fn (): array => [
            'status' => TaskStatus::Verified,
            'proof_note' => fake()->sentence(),
            'submitted_at' => now(),
            'verified_at' => now(),
        ]);
    }
}
