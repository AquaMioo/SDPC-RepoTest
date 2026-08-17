<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->catchPhrase();

        return [
            'team_id' => Team::factory(),
            'created_by' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraphs(3, true),
            'objectives' => implode("\n", fake()->sentences(3)),
            'category' => fake()->randomElement([
                'Web application',
                'Mobile application',
                'Management / inventory system',
                'E-commerce or booking',
                'Data & analytics',
            ]),
            'industry' => fake()->randomElement([
                'Retail & grocery', 'Logistics', 'Food service',
                'Healthcare', 'Education', 'Other',
            ]),
            'status' => ProjectStatus::Open,
            'applications_open' => true,
            'published_at' => now(),
        ];
    }

    /**
     * Indicate that the project is an unpublished draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Draft,
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the project is awaiting admin screening.
     */
    public function pendingReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::PendingReview,
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the project has students working on it.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::InProgress,
            'applications_open' => false,
        ]);
    }

    /**
     * Indicate that the project has been delivered.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Completed,
            'applications_open' => false,
        ]);
    }

    /**
     * Indicate that the project has been archived by the client.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Archived,
            'applications_open' => false,
        ]);
    }

    /**
     * Indicate that intake has been paused on an otherwise open posting.
     */
    public function closedToApplications(): static
    {
        return $this->state(fn (array $attributes) => [
            'applications_open' => false,
        ]);
    }
}
