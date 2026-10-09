<?php

namespace Database\Factories;

use App\Enums\AddendumPaymentStatus;
use App\Models\Addendum;
use App\Models\AddendumPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AddendumPayment>
 */
class AddendumPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'addendum_id' => Addendum::factory(),
            'milestone' => 1,
            'percentage' => Addendum::DOWN_PAYMENT_PERCENT,
            'amount' => 300000,
            'status' => AddendumPaymentStatus::Pending,
            'invoice_number' => 'INV-'.fake()->unique()->bothify('SDPC-####-###-A1-M#'),
        ];
    }
}
