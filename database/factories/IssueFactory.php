<?php

namespace Database\Factories;

use App\Enums\IssueCategory;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reported_user_id' => User::factory(),
            'category' => fake()->randomElement(IssueCategory::cases()),
            'description' => fake()->sentence(12),
            'status' => IssueStatus::Pending,
        ];
    }

    /**
     * A report about a posting rather than only about a person.
     *
     * The account stays named: closing the posting alone leaves whoever wrote
     * it free to write another.
     */
    public function aboutPosting(?Project $project = null): static
    {
        return $this->state(fn (): array => [
            'category' => IssueCategory::MisleadingPosting,
            'reported_project_id' => $project?->id ?? Project::factory(),
        ]);
    }

    /**
     * A report an administrator has picked up but not finished.
     */
    public function inReview(): static
    {
        return $this->state(fn (): array => ['status' => IssueStatus::InReview]);
    }

    /**
     * A report that has been decided.
     */
    public function resolved(string $resolution = 'Warned'): static
    {
        return $this->state(fn (): array => [
            'status' => IssueStatus::Resolved,
            'resolution' => $resolution,
            'handled_by' => User::factory()->admin(),
            'handled_at' => now(),
        ]);
    }
}
