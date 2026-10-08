<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentProfile>
 */
class StudentProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'headline' => fake()->jobTitle(),
            'biography' => fake()->paragraph(),
            'school_id' => School::factory(),
            'course_id' => Course::factory(),
            'year_level' => fake()->numberBetween(1, 4),
            'github_url' => 'https://github.com/'.fake()->userName(),
            'portfolio_url' => fake()->url(),
            'is_available' => true,
            'hourly_rate' => fake()->numberBetween(150, 600),
            /*
             * Real figures since 2026-10-09: written by CompleteProject, so a
             * new profile has none. A random count made most factory students
             * "experienced" and tripped HiringRule — use experienced().
             */
            'rating_average' => 0,
            'ratings_count' => 0,
            'completed_projects_count' => 0,
        ];
    }

    /**
     * Indicate that the student has finished a project (HiringRule).
     */
    public function experienced(int $completed = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_projects_count' => $completed,
        ]);
    }

    /**
     * Indicate that the student is not taking new work.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }
}
