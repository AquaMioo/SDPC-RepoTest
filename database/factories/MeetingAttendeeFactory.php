<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingAttendee>
 */
class MeetingAttendeeFactory extends Factory
{
    /**
     * Somebody in the call right now.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'user_id' => User::factory(),
            'joined_at' => now(),
            'last_seen_at' => now(),
            'left_at' => null,
        ];
    }

    /**
     * Pressed Leave.
     */
    public function left(): static
    {
        return $this->state(fn (): array => [
            'left_at' => now(),
        ]);
    }

    /**
     * Closed the tab without leaving: the heartbeat stopped long ago.
     */
    public function lapsed(): static
    {
        return $this->state(fn (): array => [
            'joined_at' => now()->subMinutes(10),
            'last_seen_at' => now()->subSeconds(MeetingAttendee::PRESENCE_WINDOW + 30),
        ]);
    }
}
