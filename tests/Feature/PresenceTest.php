<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who is here, as opposed to who is on the team.
 *
 * The client dashboard used to print "Active" beside every member as a plain
 * word, so a student who had signed out still showed a green dot to the
 * business that hired them. Presence is a stamp now, and these pin the three
 * ways it ends: it is written while you move around, cleared when you sign
 * out, and it goes stale on its own if you neither.
 */
class PresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_account_is_not_here_yet(): void
    {
        $user = User::factory()->student()->approved()->create();

        $this->assertNull($user->last_seen_at);
        $this->assertFalse($user->isOnline());
    }

    public function test_moving_around_the_site_stamps_the_account(): void
    {
        $user = User::factory()->student()->approved()->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['current_team' => $user->currentTeam]))
            ->assertOk();

        $this->assertNotNull($user->fresh()->last_seen_at);
        $this->assertTrue($user->fresh()->isOnline());
    }

    public function test_signing_out_stops_the_account_counting_as_here(): void
    {
        $user = User::factory()->student()->approved()->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['current_team' => $user->currentTeam]));

        $this->assertTrue($user->fresh()->isOnline());

        $this->actingAs($user)->post(route('logout'));

        /*
         * Null rather than an old timestamp: leaving is deliberate, and must
         * not wait out the window the way a closed tab does.
         */
        $this->assertNull($user->fresh()->last_seen_at);
        $this->assertFalse($user->fresh()->isOnline());
    }

    public function test_a_stamp_older_than_the_window_stops_counting(): void
    {
        $user = User::factory()->student()->approved()->create();

        $user->forceFill([
            'last_seen_at' => now()->subMinutes(User::PRESENCE_WINDOW_MINUTES + 1),
        ])->saveQuietly();

        $this->assertFalse($user->fresh()->isOnline());

        $user->forceFill([
            'last_seen_at' => now()->subMinutes(User::PRESENCE_WINDOW_MINUTES - 1),
        ])->saveQuietly();

        $this->assertTrue($user->fresh()->isOnline());
    }

    public function test_the_stamp_is_not_rewritten_on_every_request(): void
    {
        $user = User::factory()->student()->approved()->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['current_team' => $user->currentTeam]));

        $first = $user->fresh()->last_seen_at;

        $this->actingAs($user)
            ->get(route('dashboard', ['current_team' => $user->currentTeam]));

        /*
         * A write per page view would put the users table behind every
         * request in the app, for a figure read to the nearest few minutes.
         */
        $this->assertTrue($first->equalTo($user->fresh()->last_seen_at));
    }

    public function test_presence_does_not_disturb_the_account_record(): void
    {
        $user = User::factory()->student()->approved()->create();
        $touchedAt = $user->updated_at;

        $this->travel(2)->minutes();

        $this->actingAs($user)
            ->get(route('dashboard', ['current_team' => $user->currentTeam]));

        /* A heartbeat is not somebody editing their account. */
        $this->assertTrue($touchedAt->equalTo($user->fresh()->updated_at));
    }
}
