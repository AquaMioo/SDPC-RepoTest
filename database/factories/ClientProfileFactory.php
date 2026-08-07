<?php

namespace Database\Factories;

use App\Enums\VerificationStatus;
use App\Models\ClientProfile;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientProfile>
 */
class ClientProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'business_name' => fake()->company(),
            'business_description' => fake()->paragraph(),
            'owner_name' => fake()->name(),
            'address' => fake()->streetAddress(),
            'city' => 'Sample City',
            'province' => 'Sample Province',
            'phone_number' => fake()->phoneNumber(),
            'contact_email' => fake()->companyEmail(),
            'website_url' => fake()->url(),
            'facebook_url' => 'https://facebook.com/'.fake()->userName(),
            'verification_status' => VerificationStatus::Unverified,
        ];
    }

    /**
     * Indicate that the business has submitted a permit for review.
     */
    public function pendingVerification(): static
    {
        return $this->state(fn (array $attributes) => [
            'permit_path' => 'permits/'.fake()->uuid().'.pdf',
            'verification_status' => VerificationStatus::Pending,
        ]);
    }

    /**
     * Indicate that an administrator has verified the business.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'permit_path' => 'permits/'.fake()->uuid().'.pdf',
            'verification_status' => VerificationStatus::Verified,
            'verified_at' => now(),
        ]);
    }
}
