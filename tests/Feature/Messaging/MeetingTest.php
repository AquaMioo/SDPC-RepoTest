<?php

namespace Tests\Feature\Messaging;

use App\Enums\ApplicationStatus;
use App\Events\MeetingScheduled;
use App\Events\MeetingStarted;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * A video meeting is another door into a thread, so it answers to the thread's
 * own participant check and nothing weaker. Agora has no idea who belongs in a
 * channel — a token is the only thing between a channel name and anybody who
 * has it — so most of what matters here is who is kept out.
 */
class MeetingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'agora.enabled' => true,
            'agora.app_id' => '0123456789abcdef0123456789abcdef',
            'agora.app_certificate' => 'fedcba9876543210fedcba9876543210',
            'agora.token_ttl' => 3600,
        ]);
    }

    public function test_either_side_can_open_a_meeting_and_gets_a_token(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        foreach ([$client, $student] as $user) {
            $response = $this->actingAs($user)
                ->postJson(route('meetings.store', [
                    'current_team' => $user->currentTeam,
                    'conversation' => $thread,
                ]))
                ->assertCreated();

            $this->assertSame($thread->id, $response->json('meeting.conversationId'));
            $this->assertStringStartsWith('007', $response->json('token.token'));
            $this->assertSame($response->json('meeting.channel'), $response->json('token.channel'));
            $this->assertSame($user->id, $response->json('token.uid'));
        }

        $this->assertSame(2, Meeting::count());
    }

    public function test_somebody_outside_the_thread_cannot_open_a_meeting_on_it(): void
    {
        [, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);
        $outsider = User::factory()->student()->approved()->create();

        $this->actingAs($outsider)
            ->postJson(route('meetings.store', [
                'current_team' => $outsider->currentTeam,
                'conversation' => $thread,
            ]))
            ->assertForbidden();

        $this->assertSame(0, Meeting::count());
    }

    public function test_the_other_side_can_join_a_meeting_it_did_not_start(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);
        $meeting = $this->meeting($thread, $client);

        $response = $this->actingAs($student)
            ->postJson(route('meetings.token', [
                'current_team' => $student->currentTeam,
                'meeting' => $meeting,
            ]))
            ->assertOk();

        /* Scoped to this joiner, not to whoever placed the call. */
        $this->assertSame($student->id, $response->json('token.uid'));
        $this->assertSame($meeting->channel_name, $response->json('token.channel'));
    }

    public function test_a_stranger_cannot_get_a_token_for_somebody_elses_meeting(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);
        $meeting = $this->meeting($thread, $client);
        $outsider = User::factory()->client()->approved()->verifiedBusiness()->create();

        $this->actingAs($outsider)
            ->postJson(route('meetings.token', [
                'current_team' => $outsider->currentTeam,
                'meeting' => $meeting,
            ]))
            ->assertForbidden();
    }

    public function test_a_finished_meeting_issues_no_more_tokens(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);
        $meeting = $this->meeting($thread, $client);

        $this->actingAs($client)
            ->patchJson(route('meetings.end', [
                'current_team' => $client->currentTeam,
                'meeting' => $meeting,
            ]))
            ->assertOk();

        /*
         * Otherwise a channel name left in somebody's console would still let
         * them back in after the call was over.
         */
        $this->actingAs($student)
            ->postJson(route('meetings.token', [
                'current_team' => $student->currentTeam,
                'meeting' => $meeting,
            ]))
            ->assertGone();
    }

    public function test_hanging_up_twice_is_not_an_error(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);
        $meeting = $this->meeting($thread, $client);

        /*
         * A URL each: the team slug in the path is the acting user's own, and
         * EnsureTeamMembership rejects a student arriving on the client's.
         */
        $endAs = fn (User $user): string => route('meetings.end', [
            'current_team' => $user->currentTeam,
            'meeting' => $meeting,
        ]);

        $this->actingAs($client)->patchJson($endAs($client))->assertOk();
        $endedAt = $meeting->fresh()->ended_at;

        /* Both sides leaving together is the normal case, not a race to lose. */
        $this->actingAs($student)->patchJson($endAs($student))->assertOk();

        $this->assertTrue($endedAt->equalTo($meeting->fresh()->ended_at));
    }

    public function test_opening_a_meeting_invites_the_other_side_over_the_thread_channel(): void
    {
        Event::fake([MeetingStarted::class]);

        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        $this->actingAs($client)
            ->postJson(route('meetings.store', [
                'current_team' => $client->currentTeam,
                'conversation' => $thread,
            ]))
            ->assertCreated();

        Event::assertDispatched(
            MeetingStarted::class,
            fn (MeetingStarted $event): bool => $event->meeting->conversation_id === $thread->id
        );
    }

    public function test_the_invitation_carries_no_token(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);
        $meeting = $this->meeting($thread, $client);

        /*
         * The broadcast says a call is happening, not how to join it. Anyone
         * listening on the channel has to ask for their own token over HTTP,
         * where the participant check runs again against the authenticated
         * user rather than against whoever the socket belongs to.
         */
        $payload = (new MeetingStarted($meeting))->broadcastWith();

        $this->assertArrayNotHasKey('token', $payload);
        $this->assertArrayNotHasKey('channel', $payload);
    }

    public function test_a_meeting_can_be_booked_for_later_and_carries_no_token(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        $response = $this->actingAs($client)
            ->postJson(route('meetings.store', [
                'current_team' => $client->currentTeam,
                'conversation' => $thread,
            ]), ['scheduled_at' => now()->addDay()->toIso8601String()])
            ->assertCreated();

        /*
         * Nothing to join yet, and a token minted now would have expired by
         * the time there was.
         */
        $this->assertNull($response->json('token'));
        $this->assertTrue($response->json('meeting.isScheduled'));
        $this->assertNull($response->json('meeting.startedAt'));
    }

    public function test_booking_invites_the_other_side_as_a_diary_entry_not_a_ringing_phone(): void
    {
        Event::fake([MeetingScheduled::class, MeetingStarted::class]);

        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        $this->actingAs($client)
            ->postJson(route('meetings.store', [
                'current_team' => $client->currentTeam,
                'conversation' => $thread,
            ]), ['scheduled_at' => now()->addDay()->toIso8601String()])
            ->assertCreated();

        Event::assertDispatched(MeetingScheduled::class);
        Event::assertNotDispatched(MeetingStarted::class);
    }

    public function test_joining_a_booked_meeting_is_what_starts_it(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        $meeting = $thread->meetings()->create([
            'created_by' => $client->id,
            'channel_name' => Meeting::newChannelName(),
            'scheduled_at' => now()->addHour(),
            'started_at' => null,
        ]);

        $this->assertTrue($meeting->isScheduled());

        $this->actingAs($student)
            ->postJson(route('meetings.token', [
                'current_team' => $student->currentTeam,
                'meeting' => $meeting,
            ]))
            ->assertOk();

        /*
         * started_at means somebody was there. A meeting nobody turned up to
         * must not read as one that ran.
         */
        $this->assertNotNull($meeting->fresh()->started_at);
        $this->assertFalse($meeting->fresh()->isScheduled());
    }

    public function test_a_time_in_the_past_is_refused(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        $this->actingAs($client)
            ->postJson(route('meetings.store', [
                'current_team' => $client->currentTeam,
                'conversation' => $thread,
            ]), ['scheduled_at' => now()->subHour()->toIso8601String()])
            ->assertJsonValidationErrors('scheduled_at');

        $this->assertSame(0, Meeting::count());
    }

    public function test_the_thread_lists_only_meetings_still_worth_showing(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        $soon = $thread->meetings()->create([
            'created_by' => $client->id,
            'channel_name' => Meeting::newChannelName(),
            'scheduled_at' => now()->addHour(),
        ]);

        /* Long gone, and one already joined: neither belongs in the list. */
        $thread->meetings()->create([
            'created_by' => $client->id,
            'channel_name' => Meeting::newChannelName(),
            'scheduled_at' => now()->subDays(2),
        ]);

        $thread->meetings()->create([
            'created_by' => $client->id,
            'channel_name' => Meeting::newChannelName(),
            'scheduled_at' => now()->addHours(2),
            'started_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('messages.show', [
                'current_team' => $student->currentTeam,
                'conversation' => $thread,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('active.meetings', 1)
                ->where('active.meetings.0.id', $soon->id));
    }

    public function test_the_routes_are_absent_while_agora_is_switched_off(): void
    {
        config(['agora.enabled' => false]);

        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        $this->actingAs($client)
            ->postJson(route('meetings.store', [
                'current_team' => $client->currentTeam,
                'conversation' => $thread,
            ]))
            ->assertNotFound();
    }

    /**
     * A client and a student joined by an application on one posting.
     *
     * @return array{0: User, 1: User, 2: Project}
     */
    private function pair(): array
    {
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
        $student = User::factory()->student()->approved()->create();

        $project = Project::factory()->create(['team_id' => $client->current_team_id]);

        Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Pending,
        ]);

        return [$client->fresh(), $student->fresh(), $project];
    }

    private function thread(Project $project, User $student): Conversation
    {
        return Conversation::create([
            'project_id' => $project->id,
            'user_id' => $student->id,
        ]);
    }

    private function meeting(Conversation $thread, User $creator): Meeting
    {
        return $thread->meetings()->create([
            'created_by' => $creator->id,
            'channel_name' => Meeting::newChannelName(),
            'started_at' => now(),
        ]);
    }
}
