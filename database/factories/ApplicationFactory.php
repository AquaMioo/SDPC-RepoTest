<?php

namespace Database\Factories;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
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
            'user_id' => User::factory()->student(),
            'status' => ApplicationStatus::Pending,
            'source' => ApplicationSource::Applied,
            'cover_letter' => fake()->paragraph(),
            'proposed_rate' => fake()->numberBetween(200, 800),
        ];
    }

    /**
     * Indicate that the client has shortlisted the applicant.
     */
    public function shortlisted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Shortlisted,
            'responded_at' => now(),
        ]);
    }

    /**
     * Indicate that the applicant has been accepted onto the project.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Accepted,
            'responded_at' => now(),
        ]);
    }

    /**
     * Indicate that the applicant has been rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Rejected,
            'responded_at' => now(),
        ]);
    }

    /**
     * Indicate that the student withdrew their own application.
     */
    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Withdrawn,
        ]);
    }

    /**
     * Indicate that the client initiated the link by inviting the student.
     */
    public function invited(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => ApplicationSource::Invited,
            'cover_letter' => null,
        ]);
    }
}
