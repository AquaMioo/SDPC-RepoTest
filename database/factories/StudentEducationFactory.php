<?php

namespace Database\Factories;

use App\Models\StudentEducation;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentEducation>
 */
class StudentEducationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $from = fake()->numberBetween(2015, 2022);

        return [
            'student_profile_id' => StudentProfile::factory(),
            'school' => fake()->company().' College',
            'area_of_study' => fake()->randomElement([
                'Information Technology',
                'Computer Science',
                'Information Systems',
            ]),
            'from_year' => $from,
            'to_year' => $from + 4,
        ];
    }

    /**
     * A degree still being taken, so the end year reads as expected.
     */
    public function ongoing(): static
    {
        return $this->state(fn (): array => [
            'from_year' => (int) now()->year - 2,
            'to_year' => (int) now()->year + 2,
        ]);
    }

    /**
     * Schooling finished, so the end year is stated plainly.
     */
    public function finished(): static
    {
        return $this->state(fn (): array => [
            'from_year' => (int) now()->year - 8,
            'to_year' => (int) now()->year - 4,
        ]);
    }
}
