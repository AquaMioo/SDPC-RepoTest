<?php

namespace Database\Factories;

use App\Enums\AgreementParty;
use App\Enums\ContractRequestStatus;
use App\Enums\ContractRequestType;
use App\Models\Agreement;
use App\Models\ContractRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractRequest>
 */
class ContractRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => (new Agreement)->getMorphClass(),
            'subject_id' => Agreement::factory(),
            'type' => ContractRequestType::Denial,
            'party' => AgreementParty::Student,
            'reason' => fake()->sentence(10),
            'status' => ContractRequestStatus::Pending,
        ];
    }
}
