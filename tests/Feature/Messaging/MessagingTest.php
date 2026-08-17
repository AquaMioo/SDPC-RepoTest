<?php

namespace Tests\Feature\Messaging;

use App\Enums\ApplicationStatus;
use App\Enums\TeamRole;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Messaging is scoped to a posting and a student, and a thread only exists
 * where an application already links them. That rule is the entire
 * authorisation model, so most of what matters here is who is kept out.
 */
class MessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_roles_can_open_the_inbox(): void
    {
        [$client, $student] = $this->pair();

        foreach ([$client, $student] as $user) {
            $this->actingAs($user)
                ->get(route('messages.index', ['current_team' => $user->currentTeam]))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->component('messaging/index'));
        }
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        [$client] = $this->pair();

        $this->get(route('messages.index', ['current_team' => $client->currentTeam]))
            ->assertRedirect(route('login'));
    }

    public function test_an_inbox_starts_empty(): void
    {
        [$client] = $this->pair();

        $this->actingAs($client)
            ->get(route('messages.index', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('threads', 0)
                ->where('active', null));
    }

    public function test_a_client_can_open_a_thread_with_an_applicant(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);

        $this->actingAs($client)
            ->post(route('messages.store', ['current_team' => $client->currentTeam]), [
                'project_id' => $project->id,
                'user_id' => $student->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('conversations', [
            'project_id' => $project->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_opening_the_same_thread_twice_does_not_duplicate_it(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);

        $payload = ['project_id' => $project->id, 'user_id' => $student->id];
        $url = route('messages.store', ['current_team' => $client->currentTeam]);

        $this->actingAs($client)->post($url, $payload);
        $this->actingAs($client)->post($url, $payload);

        $this->assertSame(1, Conversation::count());
    }

    public function test_a_thread_can_not_be_opened_without_an_application(): void
    {
        // No application means these two have no dealings, so there is nothing
        // to talk about and no way to start.
        [$client, $student, $project] = $this->pair(applied: false);

        $this->actingAs($client)
            ->post(route('messages.store', ['current_team' => $client->currentTeam]), [
                'project_id' => $project->id,
                'user_id' => $student->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, Conversation::count());
    }

    public function test_an_outsider_can_not_open_a_thread_about_someone_elses_project(): void
    {
        [, $student, $project] = $this->pair(applied: true);
        $outsider = User::factory()->client()->approved()->create();

        $this->actingAs($outsider)
            ->post(route('messages.store', ['current_team' => $outsider->currentTeam]), [
                'project_id' => $project->id,
                'user_id' => $student->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, Conversation::count());
    }

    public function test_a_participant_can_send_and_the_other_side_reads_it(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $conversation = $this->thread($project, $student);

        $this->actingAs($client)
            ->from(route('messages.show', [
                'current_team' => $client->currentTeam,
                'conversation' => $conversation,
            ]))
            ->post(route('messages.send', [
                'current_team' => $client->currentTeam,
                'conversation' => $conversation,
            ]), ['body' => 'Are you free for a call on Thursday?'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'user_id' => $client->id,
            'body' => 'Are you free for a call on Thursday?',
        ]);

        $this->actingAs($student)
            ->get(route('messages.show', [
                'current_team' => $student->currentTeam,
                'conversation' => $conversation,
            ]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('active.messages', 1)
                ->where('active.messages.0.body', 'Are you free for a call on Thursday?')
                ->where('active.messages.0.isMine', false));
    }

    public function test_an_empty_message_is_rejected(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $conversation = $this->thread($project, $student);

        $this->actingAs($client)
            ->from(route('messages.show', [
                'current_team' => $client->currentTeam,
                'conversation' => $conversation,
            ]))
            ->post(route('messages.send', [
                'current_team' => $client->currentTeam,
                'conversation' => $conversation,
            ]), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, Message::count());
    }

    public function test_a_stranger_can_neither_read_nor_write_a_thread(): void
    {
        [, $student, $project] = $this->pair(applied: true);
        $conversation = $this->thread($project, $student);
        $stranger = User::factory()->student()->approved()->create();

        $this->actingAs($stranger)
            ->get(route('messages.show', [
                'current_team' => $stranger->currentTeam,
                'conversation' => $conversation,
            ]))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->post(route('messages.send', [
                'current_team' => $stranger->currentTeam,
                'conversation' => $conversation,
            ]), ['body' => 'Let me in.'])
            ->assertForbidden();

        $this->assertSame(0, Message::count());
    }

    public function test_a_thread_only_appears_in_its_participants_inboxes(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $this->thread($project, $student);

        $stranger = User::factory()->student()->approved()->create();

        foreach ([$client, $student] as $participant) {
            $this->actingAs($participant)
                ->get(route('messages.index', ['current_team' => $participant->currentTeam]))
                ->assertInertia(fn (AssertableInertia $page) => $page->has('threads', 1));
        }

        $this->actingAs($stranger)
            ->get(route('messages.index', ['current_team' => $stranger->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('threads', 0));
    }

    public function test_a_colleague_on_the_business_team_shares_the_thread(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $conversation = $this->thread($project, $student);

        // A thread belongs to the business, not to whoever opened it.
        $colleague = User::factory()->client()->approved()->create();
        $project->team->members()->attach($colleague, ['role' => TeamRole::Member->value]);
        $colleague->switchTeam($project->team);

        $this->actingAs($colleague->fresh())
            ->get(route('messages.show', [
                'current_team' => $project->team,
                'conversation' => $conversation,
            ]))
            ->assertOk();
    }

    public function test_an_unread_message_is_counted_then_cleared_by_reading_it(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $conversation = $this->thread($project, $student);

        $this->actingAs($client)
            ->from(route('messages.index', ['current_team' => $client->currentTeam]))
            ->post(route('messages.send', [
                'current_team' => $client->currentTeam,
                'conversation' => $conversation,
            ]), ['body' => 'Sending you the brief now.']);

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('unreadMessages', 1));

        $this->actingAs($student)
            ->get(route('messages.show', [
                'current_team' => $student->currentTeam,
                'conversation' => $conversation,
            ]))
            ->assertOk();

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('unreadMessages', 0));
    }

    public function test_your_own_message_never_comes_back_as_unread(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $conversation = $this->thread($project, $student);

        $this->actingAs($client)
            ->from(route('messages.index', ['current_team' => $client->currentTeam]))
            ->post(route('messages.send', [
                'current_team' => $client->currentTeam,
                'conversation' => $conversation,
            ]), ['body' => 'Just confirming the scope.']);

        $this->actingAs($client)
            ->get(route('client.dashboard', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('unreadMessages', 0));
    }

    public function test_a_reply_in_the_same_second_still_counts_as_unread(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $conversation = $this->thread($project, $student);

        /*
         * The whole exchange inside one frozen second. Read state used to be
         * two timestamps, and Laravel stores those to the second — so a reply
         * landing in the same second as the other side's last read looked like
         * one they had already seen, and the thread came back "read".
         */
        Carbon::setTestNow('2026-08-16 09:30:00');

        $conversation->messages()->create(['user_id' => $client->id, 'body' => 'Are you free Thursday?']);
        $conversation->forceFill(['last_message_at' => now()])->save();
        $conversation->load('latestMessage');
        $conversation->markReadFor($client);

        $conversation->refresh()->load('latestMessage');
        $conversation->markReadFor($student);

        $conversation->messages()->create(['user_id' => $student->id, 'body' => 'Yes, Thursday works.']);
        $conversation->forceFill(['last_message_at' => now()])->save();

        $conversation = $conversation->fresh();

        $this->assertTrue(
            $conversation->isUnreadFor($client),
            'The reply must reach the client even though it shares a second with their last read.',
        );
        $this->assertFalse($conversation->isUnreadFor($student));

        Carbon::setTestNow();
    }

    public function test_an_untouched_thread_is_not_unread(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $conversation = $this->thread($project, $student);

        // An opened but empty thread has nothing to report.
        $this->assertFalse($conversation->isUnreadFor($client));
        $this->assertFalse($conversation->isUnreadFor($student));
    }

    /**
     * A client who owns a posting and a student, optionally already linked by
     * an application.
     *
     * @return array{0: User, 1: User, 2: Project}
     */
    private function pair(bool $applied = false): array
    {
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
        $student = User::factory()->student()->approved()->create();

        $project = Project::factory()->create(['team_id' => $client->current_team_id]);

        if ($applied) {
            Application::factory()->create([
                'project_id' => $project->id,
                'user_id' => $student->id,
                'status' => ApplicationStatus::Pending,
            ]);
        }

        return [$client->fresh(), $student->fresh(), $project];
    }

    /**
     * An open thread between a posting and a student.
     */
    private function thread(Project $project, User $student): Conversation
    {
        return Conversation::create([
            'project_id' => $project->id,
            'user_id' => $student->id,
        ]);
    }
}
