<?php

namespace Database\Factories;

use App\Enums\DeadlineRequestStatus;
use App\Models\AgreementTask;
use App\Models\DeadlineChangeRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeadlineChangeRequest>
 */
class DeadlineChangeRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A pending ask to push a task's deadline back a week.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agreement_id' => fn (array $attributes) => AgreementTask::query()
                ->find($attributes['agreement_task_id'])
                ?->milestone
                ->agreement_id,
            'agreement_task_id' => AgreementTask::factory(),
            'agreement_milestone_id' => null,
            'previous_on' => now()->addWeek()->toDateString(),
            'proposed_on' => now()->addWeeks(2)->toDateString(),
            'reason' => fake()->sentence(),
            'status' => DeadlineRequestStatus::Pending,
        ];
    }
}
