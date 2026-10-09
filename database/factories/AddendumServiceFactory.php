<?php

namespace Database\Factories;

use App\Models\Addendum;
use App\Models\AddendumService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AddendumService>
 */
class AddendumServiceFactory extends Factory
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
            'user_id' => null,
            'objective' => fake()->words(3, true),
            'scope' => fake()->sentence(12),
        ];
    }
}
