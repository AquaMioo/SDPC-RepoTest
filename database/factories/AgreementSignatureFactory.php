<?php

namespace Database\Factories;

use App\Enums\AgreementParty;
use App\Models\Agreement;
use App\Models\AgreementSignature;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgreementSignature>
 */
class AgreementSignatureFactory extends Factory
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
            'user_id' => User::factory(),
            'party' => AgreementParty::Student,
            'signed_name' => fake()->name(),
            'acknowledgements' => ['intellectual_property', 'availability', 'confidentiality', 'schedule'],
            'signed_at' => now(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    /**
     * Indicate the signature belongs to the client side.
     */
    public function client(): static
    {
        return $this->state(fn (): array => [
            'party' => AgreementParty::Client,
        ]);
    }
}
