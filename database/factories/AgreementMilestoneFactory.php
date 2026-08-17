<?php

namespace Database\Factories;

use App\Enums\MilestoneStatus;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgreementMilestone>
 */
class AgreementMilestoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agreement_id' => Agreement::factory(),
            'position' => 1,
            'title' => fake()->randomElement(['Design', 'Build', 'Turnover']),
            'description' => fake()->sentence(10),
            'amount' => fake()->numberBetween(2, 30) * 1000,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addWeeks(3)->toDateString(),
            'status' => MilestoneStatus::Pending,
        ];
    }

    /**
     * Indicate that the client has signed the work off.
     */
    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => MilestoneStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
