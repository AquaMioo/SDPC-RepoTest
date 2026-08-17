<?php

namespace Database\Factories;

use App\Enums\VerificationProvider;
use App\Enums\VerificationStatus;
use App\Models\StudentVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentVerification>
 */
class StudentVerificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => VerificationProvider::SheerId,
            'status' => VerificationStatus::Pending,
            'external_id' => fake()->uuid(),
            'redirect_url' => fake()->url(),
        ];
    }

    /**
     * Indicate the provider confirmed the student.
     */
    public function verified(): static
    {
        return $this->state(fn (): array => [
            'status' => VerificationStatus::Verified,
            'verified_at' => now(),
        ]);
    }

    /**
     * Indicate the provider could not confirm the student.
     */
    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => VerificationStatus::Rejected,
            'failure_reason' => 'Enrolment could not be confirmed.',
        ]);
    }
}
