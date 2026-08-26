<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'created_by' => User::factory(),
            'channel_name' => Meeting::newChannelName(),
            'scheduled_at' => null,
            'started_at' => now(),
            'ended_at' => null,
        ];
    }

    /**
     * An invitation for later, which nobody has joined yet.
     */
    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'scheduled_at' => now()->addHour(),
            'started_at' => null,
        ]);
    }

    /**
     * A call that is over. Nobody may join, and its token is spent.
     */
    public function ended(): static
    {
        return $this->state(fn (): array => [
            'started_at' => now()->subMinutes(30),
            'ended_at' => now()->subMinutes(5),
        ]);
    }
}
