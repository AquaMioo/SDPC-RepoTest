<?php

namespace Database\Factories;

use App\Enums\SkillType;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Skill>
 */
class SkillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'type' => SkillType::General,
        ];
    }

    /**
     * Indicate that the skill is a programming language.
     */
    public function language(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => SkillType::Language,
        ]);
    }

    /**
     * Indicate that the skill is a framework.
     */
    public function framework(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => SkillType::Framework,
        ]);
    }

    /**
     * Indicate that the skill is a database.
     */
    public function database(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => SkillType::Database,
        ]);
    }
}
