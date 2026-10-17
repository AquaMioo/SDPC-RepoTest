<?php

namespace Database\Factories;

use App\Models\AnnouncementImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnouncementImage>
 */
class AnnouncementImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => 'announcements/'.fake()->uuid().'.webp',
            'original_name' => fake()->word().'.png',
            'position' => fake()->numberBetween(1, AnnouncementImage::MAX),
        ];
    }
}
