<?php

namespace Database\Factories;

use App\Enums\AddendumPaymentStatus;
use App\Enums\AddendumStatus;
use App\Models\Addendum;
use App\Models\Agreement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Addendum>
 */
class AddendumFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agreement_id' => Agreement::factory()->active(),
            'sequence' => 1,
            'reference' => 'SDPC-'.now()->year.'-'.fake()->unique()->numberBetween(100, 9999).'-A1',
            'status' => AddendumStatus::Draft,
            'total_amount' => null,
        ];
    }

    /**
     * Section IV's target amount set, in whole pesos.
     */
    public function withAmount(int $pesos = 10000): static
    {
        return $this->state(fn (array $attributes) => [
            'total_amount' => $pesos,
        ]);
    }

    /**
     * Signed by both sides, with its two milestones waiting to be paid.
     */
    public function executed(int $pesos = 10000): static
    {
        return $this->withAmount($pesos)
            ->state(fn (array $attributes) => [
                'status' => AddendumStatus::Active,
                'client_signed_name' => fake()->name(),
                'client_signed_at' => now(),
                'client_gcash_number' => '09171234567',
                'student_signed_name' => fake()->name(),
                'student_signed_at' => now(),
                'student_gcash_number' => '09281234567',
                'executed_at' => now(),
            ])
            ->afterCreating(function (Addendum $addendum): void {
                foreach ($addendum->milestoneAmounts() as $milestone => $amount) {
                    $addendum->payments()->create([
                        'milestone' => $milestone,
                        'percentage' => $milestone === 1 ? Addendum::DOWN_PAYMENT_PERCENT : 100 - Addendum::DOWN_PAYMENT_PERCENT,
                        'amount' => $amount,
                        'status' => AddendumPaymentStatus::Pending,
                        'invoice_number' => 'INV-'.$addendum->reference.'-M'.$milestone,
                    ]);
                }
            });
    }
}
