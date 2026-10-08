<?php

namespace Database\Factories;

use App\Models\ProjectRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectRating>
 */
class ProjectRatingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => User::factory()->student(),
            'project_title' => fake()->catchPhrase(),
            'client_name' => fake()->company(),
            'rating' => fake()->numberBetween(1, 5),
            'feedback' => fake()->optional()->sentence(),
        ];
    }
}
