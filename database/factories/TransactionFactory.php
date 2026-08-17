<?php

namespace Database\Factories;

use App\Enums\PaymentWallet;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Agreement;
use App\Models\Project;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
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
            'agreement_milestone_id' => null,
            'project_id' => Project::factory(),
            'payer_team_id' => Team::factory(),
            'payee_user_id' => User::factory(),
            'type' => TransactionType::Milestone,
            'status' => TransactionStatus::Pending,
            'wallet' => PaymentWallet::Unset,
            'amount' => fake()->numberBetween(2, 30) * 1000,
            'description' => fake()->sentence(5),
            'reference' => 'TXN-'.fake()->unique()->numerify('########'),
            'benefit_period_start' => now()->startOfMonth()->toDateString(),
            'benefit_period_end' => now()->endOfMonth()->toDateString(),
        ];
    }

    /**
     * Indicate the student confirmed the money arrived.
     */
    public function settled(): static
    {
        return $this->state(fn (): array => [
            'status' => TransactionStatus::Settled,
            'wallet' => PaymentWallet::GCash,
            'posted_at' => now()->subDay(),
            'settled_at' => now(),
        ]);
    }
}
