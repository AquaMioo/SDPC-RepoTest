<?php

namespace Database\Factories;

use App\Enums\AgreementStatus;
use App\Models\Agreement;
use App\Models\Application;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agreement>
 */
class AgreementFactory extends Factory
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
            'application_id' => Application::factory(),
            'team_id' => Team::factory(),
            'student_id' => User::factory(),
            'reference' => 'SDPC-'.now()->year.'-'.fake()->unique()->numberBetween(100, 9999),
            'version' => 1,
            'status' => AgreementStatus::Draft,
            'scope_summary' => fake()->sentence(12),
            'deliverables' => [
                fake()->sentence(6),
                fake()->sentence(6),
            ],
            'intellectual_property_terms' => fake()->paragraph(2),
            'confidentiality_terms' => fake()->paragraph(2),
            'academic_terms' => fake()->paragraph(2),
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addMonths(2)->toDateString(),
            'total_amount' => 0,
        ];
    }

    /**
     * Indicate that the terms are settled and signatures are outstanding.
     */
    public function awaitingSignatures(): static
    {
        return $this->state(fn (): array => [
            'status' => AgreementStatus::AwaitingSignatures,
        ]);
    }

    /**
     * Indicate that both sides have signed and work has started.
     */
    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => AgreementStatus::Active,
            'activated_at' => now(),
        ]);
    }
}
