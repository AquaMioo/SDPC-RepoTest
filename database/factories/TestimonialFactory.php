<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
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
            'user_id' => User::factory(),
            'body' => fake()->paragraph(3),
            'author_title' => fake()->randomElement(['Owner', 'Manager', 'Founder', 'Operations Head']),
        ];
    }
}
