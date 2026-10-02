<?php

namespace Database\Factories;

use App\Enums\MemorandumSection;
use App\Models\Agreement;
use App\Models\AgreementRequirement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgreementRequirement>
 */
class AgreementRequirementFactory extends Factory
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
            'section' => fake()->randomElement(MemorandumSection::requirementSections()),
            'user_id' => User::factory(),
            'body' => fake()->sentence(12),
        ];
    }
}
