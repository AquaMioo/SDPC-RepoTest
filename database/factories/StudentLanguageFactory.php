<?php

namespace Database\Factories;

use App\Enums\LanguageProficiency;
use App\Models\StudentLanguage;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentLanguage>
 */
class StudentLanguageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_profile_id' => StudentProfile::factory(),
            /*
             * Unique per profile in the schema, so the default cannot be a
             * fixed string or a second one on the same profile collides.
             */
            'name' => fake()->unique()->languageCode(),
            'proficiency' => fake()->randomElement(LanguageProficiency::cases()),
        ];
    }

    /**
     * A first language.
     */
    public function native(): static
    {
        return $this->state(fn (): array => [
            'proficiency' => LanguageProficiency::NativeOrBilingual,
        ]);
    }
}
