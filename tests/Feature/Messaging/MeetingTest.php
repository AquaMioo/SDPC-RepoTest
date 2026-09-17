<?php

namespace Tests\Feature\Messaging;

use App\Enums\ApplicationStatus;
use App\Enums\TeamRole;
use App\Events\MeetingScheduled;
use App\Events\MeetingStarted;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Messaging\CallEnded;
use App\Notifications\Messaging\IncomingCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
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
        foreach (['client', 'student'] as $side) {
            /* A thread each: on one thread the second press joins the first call. */
            [$client, $student, $project] = $this->pair();
            $thread = $this->thread($project, $student);
            $user = $side === 'client' ? $client : $student;

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

    /**
     * The phone ringing: everyone else in the thread is rung on their own
     * channel, wherever they are on the platform — never the caller.
     */
    public function test_starting_a_call_rings_everyone_else_in_the_thread(): void
    {
        Notification::fake();

        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        $this->actingAs($student)
            ->postJson(route('meetings.store', [
                'current_team' => $student->currentTeam,
                'conversation' => $thread,
            ]))
            ->assertCreated();

        $meeting = Meeting::sole();

        Notification::assertSentTo(
            $client,
            IncomingCall::class,
            function (IncomingCall $notification, array $channels) use ($meeting, $student, $client): bool {
                $payload = $notification->toArray($client);

                return in_array('broadcast', $channels, true)
                    && $notification->broadcastType() === 'call.incoming'
                    && $payload['meeting_id'] === $meeting->id
                    && $payload['caller_name'] === $student->name
                    && ! array_key_exists('token', $payload);
            },
        );
        Notification::assertNotSentTo($student, IncomingCall::class);
    }

    public function test_a_booked_meeting_does_not_ring(): void
    {
        Notification::fake();

        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        $this->actingAs($student)
            ->postJson(route('meetings.store', [
                'current_team' => $student->currentTeam,
                'conversation' => $thread,
            ]), ['scheduled_at' => now()->addDay()->toIso8601String()])
            ->assertCreated();

        Notification::assertNotSentTo($client, IncomingCall::class);
        Notification::assertNotSentTo($student, IncomingCall::class);
    }

    public function test_hanging_up_stops_the_ringing_for_everyone_else(): void
    {
        Notification::fake();

        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);
        $meeting = $this->meeting($thread, $student);

        $this->actingAs($student)
            ->patchJson(route('meetings.end', [
                'current_team' => $student->currentTeam,
                'meeting' => $meeting,
            ]))
            ->assertOk();

        Notification::assertSentTo(
            $client,
            CallEnded::class,
            fn (CallEnded $notification): bool => $notification->toArray($client)['meeting_id'] === $meeting->id,
        );
        Notification::assertNotSentTo($student, CallEnded::class);
    }

    /**
     * The ring is a courtesy on top of a call that already exists: a
     * broadcaster that cannot be reached must not fail the call.
     */
    public function test_an_unreachable_broadcaster_never_fails_the_call(): void
    {
        [, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 1,
        ]);

        $this->actingAs($student)
            ->postJson(route('meetings.store', [
                'current_team' => $student->currentTeam,
                'conversation' => $thread,
            ]))
            ->assertCreated();
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

    /**
     * The group-call bug: a second person pressing Call opened a second call,
     * and the group split between two rooms without knowing.
     */
    public function test_pressing_call_while_a_call_is_running_joins_it(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        $opened = $this->startCallAs($client, $thread)->assertCreated();

        Notification::fake();

        $joined = $this->startCallAs($student, $thread)->assertOk();

        $this->assertSame(1, Meeting::count());
        $this->assertSame($opened->json('meeting.id'), $joined->json('meeting.id'));
        $this->assertSame($opened->json('token.channel'), $joined->json('token.channel'));
        $this->assertSame($student->id, $joined->json('token.uid'));

        /* Joining a call is not starting one: nobody is rung a second time. */
        Notification::assertNothingSent();
    }

    /**
     * A full group chat: a team of four and the client, five people, one call.
     */
    public function test_everyone_in_a_full_group_chat_ends_up_in_the_same_call(): void
    {
        [$client, $student, $mate, $thread] = $this->groupThread();
        $others = $this->fillTeam($thread);

        $this->assertSame(Team::MAX_MEMBERS + 1, $thread->participants()->count());

        $meetingId = $this->startCallAs($client, $thread)->assertCreated()->json('meeting.id');

        $this->startCallAs($student, $thread)->assertOk();

        foreach ([$mate, ...$others] as $member) {
            $response = $this->actingAs($member)
                ->postJson(route('meetings.token', [
                    'current_team' => $member->currentTeam,
                    'meeting' => $meetingId,
                ]))
                ->assertOk();
        }

        $this->assertSame(1, Meeting::count());
        $this->assertSame(Team::MAX_MEMBERS + 1, Meeting::sole()->attendees()->present()->count());

        /* Every tile can be named, whoever turns up. */
        $uids = collect($response->json('people'))->pluck('uid')->sort()->values()->all();
        $expected = collect([$client, $student, $mate, ...$others])->pluck('id')->sort()->values()->all();

        $this->assertSame($expected, $uids);
    }

    public function test_a_call_everybody_has_abandoned_is_not_joined_again(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        /* The last person closed the tab an hour ago and never pressed Leave. */
        $stale = $this->meeting($thread, $client);
        MeetingAttendee::factory()->lapsed()->create(['meeting_id' => $stale->id, 'user_id' => $client->id]);

        $response = $this->startCallAs($student, $thread)->assertCreated();

        $this->assertNotSame($stale->id, $response->json('meeting.id'));
        $this->assertSame(2, Meeting::count());
    }

    public function test_leaving_keeps_the_call_running_for_everyone_still_in_it(): void
    {
        Notification::fake();

        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);
        $meeting = $this->meeting($thread, $client);
        $meeting->markPresent($client);
        $meeting->markPresent($student);

        $this->leaveAs($client, $meeting)->assertOk();

        $this->assertNull($meeting->fresh()->ended_at);
        $this->assertSame(
            [$student->id],
            $meeting->attendees()->present()->pluck('user_id')->all(),
        );
        Notification::assertNotSentTo($student, CallEnded::class);
    }

    public function test_the_last_person_leaving_ends_the_call_and_stops_the_ringing(): void
    {
        Notification::fake();

        [$client, $student, $mate, $thread] = $this->groupThread();
        $meeting = $this->meeting($thread, $client);
        $meeting->markPresent($client);

        /* Nobody answered, so the caller gives up. */
        $this->leaveAs($client, $meeting)->assertOk();

        $this->assertNotNull($meeting->fresh()->ended_at);
        Notification::assertSentTo([$student, $mate], CallEnded::class);
        Notification::assertNotSentTo($client, CallEnded::class);
    }

    public function test_leaving_twice_ends_the_call_once(): void
    {
        Notification::fake();

        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);
        $meeting = $this->meeting($thread, $client);
        $meeting->markPresent($client);

        $this->leaveAs($client, $meeting)->assertOk();
        $endedAt = $meeting->fresh()->ended_at;

        $this->travel(5)->seconds();
        $this->leaveAs($client, $meeting)->assertOk();

        $this->assertTrue($endedAt->equalTo($meeting->fresh()->ended_at));
        Notification::assertSentToTimes($student, CallEnded::class, 1);
    }

    public function test_coming_back_after_leaving_puts_you_back_in_the_same_row(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);
        $meeting = $this->meeting($thread, $client);
        $meeting->markPresent($client);
        $meeting->markPresent($student);

        $this->leaveAs($student, $meeting)->assertOk();

        $this->actingAs($student)
            ->postJson(route('meetings.token', [
                'current_team' => $student->currentTeam,
                'meeting' => $meeting,
            ]))
            ->assertOk();

        $this->assertSame(1, $meeting->attendees()->where('user_id', $student->id)->count());
        $this->assertTrue($meeting->attendees()->present()->where('user_id', $student->id)->exists());
    }

    public function test_the_heartbeat_is_what_keeps_somebody_counted_as_in_the_call(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);
        $meeting = $this->meeting($thread, $client);
        $meeting->markPresent($client);
        $meeting->markPresent($student);

        $wait = MeetingAttendee::PRESENCE_WINDOW - 10;

        /* The client's screen keeps beating; the student's tab was closed. */
        $this->travel($wait)->seconds();
        $this->heartbeatAs($client, $meeting)->assertNoContent();

        $this->travel($wait)->seconds();
        $this->heartbeatAs($client, $meeting)->assertNoContent();

        $this->assertSame(
            [$client->id],
            $meeting->attendees()->present()->pluck('user_id')->all(),
        );
        $this->assertTrue(Meeting::query()->inProgress()->whereKey($meeting->id)->exists());

        /* And once the client stops too, the call is no longer running. */
        $this->travel(MeetingAttendee::PRESENCE_WINDOW + 1)->seconds();

        $this->assertFalse(Meeting::query()->inProgress()->whereKey($meeting->id)->exists());
    }

    public function test_a_heartbeat_for_a_finished_call_tells_the_screen_to_close(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);
        $meeting = Meeting::factory()->ended()->create([
            'conversation_id' => $thread->id,
            'created_by' => $client->id,
        ]);

        $this->heartbeatAs($student, $meeting)->assertGone();
    }

    public function test_a_stranger_can_neither_beat_nor_leave_somebody_elses_call(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);
        $meeting = $this->meeting($thread, $client);
        $meeting->markPresent($client);
        $outsider = User::factory()->student()->approved()->create();

        $this->heartbeatAs($outsider, $meeting)->assertForbidden();
        $this->leaveAs($outsider, $meeting)->assertForbidden();

        $this->assertSame(0, $meeting->attendees()->where('user_id', $outsider->id)->count());
        $this->assertNull($meeting->fresh()->ended_at);
    }

    /**
     * Somebody who declined the ring, or opened the thread afterwards, still
     * needs a way into the call the rest of the group is in.
     */
    public function test_the_thread_shows_a_running_call_and_who_is_in_it(): void
    {
        [$client, $student, $mate, $thread] = $this->groupThread();
        $meeting = $this->meeting($thread, $client);
        $meeting->markPresent($client);
        $meeting->markPresent($student);

        $this->actingAs($mate)
            ->get(route('messages.show', [
                'current_team' => $mate->currentTeam,
                'conversation' => $thread,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('active.call.id', $meeting->id)
                ->where('active.call.people', fn ($people): bool => collect($people)->sort()->values()->all()
                    === collect([$client->name, $student->name])->sort()->values()->all()));
    }

    public function test_the_thread_shows_no_call_once_everybody_has_gone(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        $abandoned = $this->meeting($thread, $client);
        MeetingAttendee::factory()->lapsed()->create(['meeting_id' => $abandoned->id, 'user_id' => $client->id]);

        $finished = Meeting::factory()->ended()->create([
            'conversation_id' => $thread->id,
            'created_by' => $client->id,
        ]);
        $finished->markPresent($client);

        $this->actingAs($student)
            ->get(route('messages.show', [
                'current_team' => $student->currentTeam,
                'conversation' => $thread,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('active.call', null));
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

    /**
     * A thread the student has brought their team into, so it is a group:
     * the student, a teammate, and the client.
     *
     * @return array{0: User, 1: User, 2: User, 3: Conversation}
     */
    private function groupThread(): array
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        $team = Team::factory()->create(['is_personal' => false]);
        $team->members()->attach($student, ['role' => TeamRole::Owner->value]);

        $mate = User::factory()->student()->approved()->create();
        $team->members()->attach($mate, ['role' => TeamRole::LeadProgrammer->value]);

        $thread->forceFill(['student_team_id' => $team->id])->save();

        /* Being on the team is not enough: the creator invites them in. */
        $thread->members()->attach($mate);

        return [$client, $student, $mate->fresh(), $thread->fresh()];
    }

    /**
     * Seat students on the thread's team until it holds Team::MAX_MEMBERS,
     * and invite each of them into the thread's group chat.
     *
     * @return list<User> the students added
     */
    private function fillTeam(Conversation $thread): array
    {
        $team = $thread->studentTeam;
        $added = [];

        while ($team->members()->count() < Team::MAX_MEMBERS) {
            $student = User::factory()->student()->approved()->create();
            $team->members()->attach($student, ['role' => TeamRole::QualityAssurance->value]);
            $thread->members()->attach($student);
            $added[] = $student->fresh();
        }

        return $added;
    }

    private function startCallAs(User $user, Conversation $thread): TestResponse
    {
        return $this->actingAs($user)
            ->postJson(route('meetings.store', [
                'current_team' => $user->currentTeam,
                'conversation' => $thread,
            ]));
    }

    private function heartbeatAs(User $user, Meeting $meeting): TestResponse
    {
        return $this->actingAs($user)
            ->postJson(route('meetings.heartbeat', [
                'current_team' => $user->currentTeam,
                'meeting' => $meeting,
            ]));
    }

    private function leaveAs(User $user, Meeting $meeting): TestResponse
    {
        return $this->actingAs($user)
            ->patchJson(route('meetings.leave', [
                'current_team' => $user->currentTeam,
                'meeting' => $meeting,
            ]));
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
