<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectAttachment>
 */
class ProjectAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->slug(3).'.pdf';

        return [
            'project_id' => Project::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 'local',
            'path' => 'project-attachments/'.fake()->uuid().'/'.$name,
            'original_name' => $name,
            'mime_type' => 'application/pdf',
            'size_in_bytes' => fake()->numberBetween(50_000, 5_000_000),
        ];
    }
}
