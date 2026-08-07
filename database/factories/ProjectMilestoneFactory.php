<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMilestone>
 */
class ProjectMilestoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->randomElement([
                'Design approval',
                'Build & internal testing',
                'Deployment & turnover',
            ]),
            'due_date' => now()->addWeeks(fake()->numberBetween(2, 10)),
            'amount' => fake()->numberBetween(3_000, 20_000),
            'position' => 0,
        ];
    }

    /**
     * Indicate that the milestone has been signed off.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
        ]);
    }
}
