<?php

namespace Tests\Feature\Messaging;

use App\Enums\AgreementStatus;
use App\Enums\ApplicationStatus;
use App\Models\Agreement;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A meeting booked in a thread reaches both dashboards.
 *
 * It used to live only inside the thread, which is the one place nobody looks
 * when planning their week.
 */
class UpcomingMeetingsOnDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_booked_meeting_appears_on_the_students_dashboard(): void
    {
        [$client, $student, $thread] = $this->thread();

        $this->book($thread, $client, now()->addDay());

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/dashboard')
                ->has('upcomingMeetings', 1)
                ->where('upcomingMeetings.0.with', $client->currentTeam->name)
                ->where('upcomingMeetings.0.scheduledByMe', false)
                ->etc());
    }

    public function test_a_booked_meeting_appears_on_the_clients_dashboard(): void
    {
        [$client, $student, $thread] = $this->thread();

        $this->book($thread, $client, now()->addDays(3));

        $this->actingAs($client)
            ->get(route('client.dashboard', ['current_team' => $client->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('client/dashboard')
                ->has('upcomingMeetings', 1)
                ->where('upcomingMeetings.0.with', $student->name)
                ->where('upcomingMeetings.0.scheduledByMe', true)
                ->etc());
    }

    /**
     * An instant, not a sentence: "tomorrow" is the viewer's to decide, because
     * the application runs on UTC and the viewer does not.
     */
    public function test_the_time_is_sent_as_an_exact_instant(): void
    {
        [$client, $student, $thread] = $this->thread();
        $at = now()->addDay()->setTime(23, 30)->startOfMinute();

        $this->book($thread, $client, $at);

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('upcomingMeetings.0.scheduledAt', $at->toIso8601String())
                ->etc());
    }

    public function test_calls_already_held_and_meetings_long_past_are_left_off(): void
    {
        [$client, $student, $thread] = $this->thread();

        // A call placed immediately is not a booking.
        Meeting::factory()->create(['conversation_id' => $thread->id, 'created_by' => $client->id]);

        // Well past its hour of grace.
        $this->book($thread, $client, now()->subHours(3));

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (Assert $page) => $page->has('upcomingMeetings', 0)->etc());
    }

    /**
     * Soonest first, so the top line is what needs attention next.
     */
    public function test_meetings_are_listed_soonest_first(): void
    {
        [$client, $student, $thread] = $this->thread();

        $later = $this->book($thread, $client, now()->addDays(5));
        $sooner = $this->book($thread, $client, now()->addHours(2));

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('upcomingMeetings.0.id', $sooner->id)
                ->where('upcomingMeetings.1.id', $later->id)
                ->etc());
    }

    /**
     * Somebody else's booking is none of their business.
     */
    public function test_a_stranger_sees_no_meetings(): void
    {
        [$client, , $thread] = $this->thread();
        $this->book($thread, $client, now()->addDay());

        $stranger = User::factory()->student()->approved()->create();

        $this->actingAs($stranger)
            ->get(route('dashboard', ['current_team' => $stranger->currentTeam]))
            ->assertInertia(fn (Assert $page) => $page->has('upcomingMeetings', 0)->etc());
    }

    /**
     * A thread closed off by a contract elsewhere cannot be opened, so its
     * meeting would be a card leading nowhere.
     */
    public function test_a_meeting_on_a_closed_off_thread_is_left_off(): void
    {
        [$client, $student, $thread] = $this->thread();
        $this->book($thread, $client, now()->addDay());

        // The student signs with a different business.
        $elsewhere = Project::factory()->create();
        Agreement::factory()->create([
            'team_id' => $elsewhere->team_id,
            'project_id' => $elsewhere->id,
            'student_id' => $student->id,
            'status' => AgreementStatus::Active,
        ]);

        $this->actingAs($client)
            ->get(route('client.dashboard', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn (Assert $page) => $page->has('upcomingMeetings', 0)->etc());
    }

    /**
     * @return array{0: User, 1: User, 2: Conversation}
     */
    private function thread(): array
    {
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
        $student = User::factory()->student()->approved()->create();

        $project = Project::factory()->create(['team_id' => $client->current_team_id]);

        Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Pending,
        ]);

        $thread = Conversation::create([
            'project_id' => $project->id,
            'user_id' => $student->id,
        ]);

        // Factories creating incidental users overwrite the URL team default.
        $client->switchTeam($client->currentTeam);

        return [$client->fresh(), $student->fresh(), $thread];
    }

    private function book(Conversation $thread, User $by, mixed $at): Meeting
    {
        return Meeting::factory()->create([
            'conversation_id' => $thread->id,
            'created_by' => $by->id,
            'scheduled_at' => $at,
            'started_at' => null,
        ]);
    }
}
