<?php

namespace Database\Factories;

use App\Models\StudentPortfolioItem;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentPortfolioItem>
 */
class StudentPortfolioItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_profile_id' => StudentProfile::factory(),
            'title' => fake()->unique()->company(),
            'role' => fake()->randomElement(['Lead developer', 'Front-end', 'Back-end', 'Full-stack']),
            'description' => fake()->sentence(14),
            'year' => fake()->numberBetween(2023, (int) now()->year),
            'url' => fake()->url(),
            'repository_url' => 'https://github.com/'.fake()->userName().'/'.fake()->slug(2),
            'is_featured' => true,
            'position' => 0,
        ];
    }
}
