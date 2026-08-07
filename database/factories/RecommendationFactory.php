<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recommendation>
 */
class RecommendationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $score = fake()->randomFloat(4, 0.4, 0.99);

        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory()->student(),
            'score' => $score,
            'compatibility_percentage' => (int) round($score * 100),
            'reason' => [
                'skill_match' => fake()->numberBetween(50, 100),
                'availability' => fake()->numberBetween(50, 100),
                'portfolio_depth' => fake()->numberBetween(30, 100),
            ],
            'generated_by' => 'null-driver',
            'generated_at' => now(),
        ];
    }
}
