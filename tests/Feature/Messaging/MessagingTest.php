<?php

namespace Tests\Feature\Messaging;

use App\Broadcasting\ConversationChannel;
use App\Enums\ApplicationStatus;
use App\Enums\TeamRole;
use App\Events\MessageSent;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\Project;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Inviting someone is the introduction, so the thread exists from that
     * moment. Without this a client invites a student, opens the inbox, and
     * finds nothing there until they go back and press Message.
     */
    public function test_inviting_a_student_opens_the_thread_straight_away(): void
    {
        [$client, $student, $project] = $this->pair(applied: false);

        $this->actingAs($client)
            ->post(route('projects.invitations.store', [
                'current_team' => $client->currentTeam,
                'project' => $project,
            ]), ['user_id' => $student->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('conversations', [
            'project_id' => $project->id,
            'user_id' => $student->id,
        ]);

        // And it is in the inbox, which is where the client just looked.
        $this->actingAs($client)
            ->get(route('messages.index', ['current_team' => $client->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('threads', 1)->etc());
    }

    /**
     * The student is the other side of the application, so they may open the
     * thread themselves rather than waiting to be written to first.
     */
    public function test_a_student_can_open_the_thread_from_their_own_side(): void
    {
        [, $student, $project] = $this->pair(applied: true);

        $this->actingAs($student)
            ->post(route('messages.store', ['current_team' => $student->currentTeam]), [
                'project_id' => $project->id,
                'user_id' => $student->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('conversations', [
            'project_id' => $project->id,
            'user_id' => $student->id,
        ]);
    }

    /**
     * The student's workflow screen is where that button lives, and without
     * these two fields on the row it has nothing to post.
     */
    public function test_the_workflow_screen_carries_what_it_takes_to_open_a_thread(): void
    {
        [, $student, $project] = $this->pair(applied: true);

        $this->actingAs($student)
            ->get(route('student.workflow', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('student/workflow')
                ->where('applications.0.projectId', $project->id)
                ->where('applications.0.canMessage', true)
                ->etc()
            );
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

    public function test_a_stranger_can_not_edit_remove_or_react_to_a_message(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $conversation = $this->thread($project, $student);

        $message = $conversation->messages()->create([
            'user_id' => $student->id,
            'body' => 'Thursday works for us',
        ]);

        $stranger = User::factory()->student()->approved()->create();

        /*
         * Nothing here is reachable from the stranger's own screen — these are
         * the requests you can still make by hand once you have read some ids
         * off somebody else's page. The buttons hiding themselves is a
         * courtesy; the check is the rule.
         */
        $this->actingAs($stranger)
            ->patch(route('messages.edit', [
                'current_team' => $stranger->currentTeam,
                'conversation' => $conversation,
                'message' => $message,
            ]), ['body' => 'I was never here'])
            ->assertForbidden();

        $this->actingAs($stranger)
            ->delete(route('messages.remove', [
                'current_team' => $stranger->currentTeam,
                'conversation' => $conversation,
                'message' => $message,
            ]))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->post(route('messages.react', [
                'current_team' => $stranger->currentTeam,
                'conversation' => $conversation,
                'message' => $message,
            ]), ['emoji' => '👍'])
            ->assertForbidden();

        $message->refresh();
        $this->assertSame('Thursday works for us', $message->body);
        $this->assertNull($message->edited_at);
        $this->assertSame(0, $message->reactions()->count());
    }

    public function test_a_participant_can_not_edit_the_other_sides_message(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $conversation = $this->thread($project, $student);

        $theirs = $conversation->messages()->create([
            'user_id' => $student->id,
            'body' => 'We can start Monday',
        ]);

        /* Being in the room is not the same as owning what was said in it. */
        $this->actingAs($client)
            ->patch(route('messages.edit', [
                'current_team' => $client->currentTeam,
                'conversation' => $conversation,
                'message' => $theirs,
            ]), ['body' => 'We can start today'])
            ->assertForbidden();

        $this->assertSame('We can start Monday', $theirs->fresh()->body);
    }

    public function test_a_message_id_from_another_thread_does_not_resolve(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $mine = $this->thread($project, $student);

        [, $otherStudent, $otherProject] = $this->pair(applied: true);
        $theirs = $this->thread($otherProject, $otherStudent);

        $elsewhere = $theirs->messages()->create([
            'user_id' => $otherStudent->id,
            'body' => 'Not for you',
        ]);

        /*
         * Pairing a thread you belong to with a message id from one you do
         * not: without the conversation_id check the route model binding
         * resolves both happily and the ownership test is the only thing left
         * standing between them.
         */
        $this->actingAs($student)
            ->patch(route('messages.edit', [
                'current_team' => $student->currentTeam,
                'conversation' => $mine,
                'message' => $elsewhere,
            ]), ['body' => 'Rewritten'])
            ->assertNotFound();

        $this->assertSame('Not for you', $elsewhere->fresh()->body);
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

    public function test_a_message_into_a_quiet_thread_raises_a_notification(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);

        $this->actingAs($student)
            ->post(route('messages.send', [
                'current_team' => $student->currentTeam,
                'conversation' => $thread,
            ]), ['body' => 'Are you free on Thursday?'])
            ->assertSessionHasNoErrors();

        $notification = $this->messageNotifications($client)->sole();

        $this->assertSame($student->name, $notification->data['sender_name']);
        $this->assertSame('Are you free on Thursday?', $notification->data['preview']);
        $this->assertSame($thread->id, $notification->data['conversation_id']);
    }

    public function test_the_sender_is_never_notified_of_their_own_message(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);

        $this->actingAs($student)
            ->post(route('messages.send', [
                'current_team' => $student->currentTeam,
                'conversation' => $thread,
            ]), ['body' => 'Are you free on Thursday?']);

        $this->assertCount(0, $this->messageNotifications($student));
    }

    public function test_a_burst_into_a_thread_already_unread_raises_one_notification(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);

        $url = route('messages.send', [
            'current_team' => $student->currentTeam,
            'conversation' => $thread,
        ]);

        /*
         * Three lines in a row from somebody who has not been answered is one
         * thread waking up, not three events. Without this the bell would
         * carry a row per message and be unreadable after any real exchange.
         */
        $this->actingAs($student)->post($url, ['body' => 'Are you free on Thursday?']);
        $this->actingAs($student)->post($url, ['body' => 'Morning would suit me']);
        $this->actingAs($student)->post($url, ['body' => 'Or Friday, if that is easier']);

        $this->assertCount(1, $this->messageNotifications($client));
    }

    public function test_a_thread_read_and_then_written_to_again_raises_another(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);

        $url = route('messages.send', [
            'current_team' => $student->currentTeam,
            'conversation' => $thread,
        ]);

        $this->actingAs($student)->post($url, ['body' => 'Are you free on Thursday?']);

        /* The client opens the thread, which marks their side read. */
        $this->actingAs($client)->get(route('messages.show', [
            'current_team' => $client->currentTeam,
            'conversation' => $thread,
        ]));

        $this->actingAs($student)->post($url, ['body' => 'Still hoping to hear back']);

        $this->assertCount(2, $this->messageNotifications($client));
    }

    /**
     * The bell rows this feature raises, and nothing else.
     *
     * A pair() linked by an application already carries an ApplicationReceived
     * row, so counting everything on the account would measure the wrong
     * thing.
     *
     * @return Collection<int, DatabaseNotification>
     */
    private function messageNotifications(User $user): Collection
    {
        return $user->fresh()->notifications()->get()
            ->where('data.type', 'message.received')
            ->values();
    }

    public function test_a_sender_can_edit_their_own_message(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);
        $message = $thread->messages()->create(['user_id' => $client->id, 'body' => 'Teh meeting is at 3']);

        $this->actingAs($client)
            ->patch(route('messages.edit', [
                'current_team' => $client->currentTeam,
                'conversation' => $thread,
                'message' => $message,
            ]), ['body' => 'The meeting is at 3'])
            ->assertSessionHasNoErrors();

        $message->refresh();

        $this->assertSame('The meeting is at 3', $message->body);
        $this->assertTrue($message->isEdited());
    }

    /**
     * Editing is for the typo you catch as you hit send, not for changing what
     * you agreed to an hour ago.
     */
    public function test_a_message_can_no_longer_be_edited_once_the_window_closes(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);
        $message = $thread->messages()->create(['user_id' => $client->id, 'body' => 'The rate is 250']);

        // One second past the window.
        $this->travel(Message::EDIT_WINDOW_SECONDS + 1)->seconds();

        $this->actingAs($client)
            ->patch(route('messages.edit', [
                'current_team' => $client->currentTeam,
                'conversation' => $thread,
                'message' => $message,
            ]), ['body' => 'The rate is 2500'])
            ->assertForbidden();

        $this->assertSame('The rate is 250', $message->fresh()->body);
    }

    public function test_a_message_can_still_be_edited_inside_the_window(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);
        $message = $thread->messages()->create(['user_id' => $client->id, 'body' => 'Teh rate is 250']);

        $this->travel(Message::EDIT_WINDOW_SECONDS - 5)->seconds();

        $this->actingAs($client)
            ->patch(route('messages.edit', [
                'current_team' => $client->currentTeam,
                'conversation' => $thread,
                'message' => $message,
            ]), ['body' => 'The rate is 250'])
            ->assertSessionHasNoErrors();

        $this->assertSame('The rate is 250', $message->fresh()->body);
    }

    /**
     * Removing has no such window — taking a message back stays available.
     */
    public function test_removing_is_not_limited_by_the_edit_window(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);
        $message = $thread->messages()->create(['user_id' => $client->id, 'body' => 'Sent by mistake']);

        $this->travel(Message::EDIT_WINDOW_SECONDS + 600)->seconds();

        $this->actingAs($client)
            ->delete(route('messages.remove', [
                'current_team' => $client->currentTeam,
                'conversation' => $thread,
                'message' => $message,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue($message->fresh()->isRemoved());
    }

    /**
     * The other side of a thread must never be able to rewrite what was said
     * to them.
     */
    public function test_the_other_participant_can_not_edit_someone_elses_message(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);
        $message = $thread->messages()->create(['user_id' => $client->id, 'body' => 'The rate is 250']);

        $this->actingAs($student)
            ->patch(route('messages.edit', [
                'current_team' => $student->currentTeam,
                'conversation' => $thread,
                'message' => $message,
            ]), ['body' => 'The rate is 2500'])
            ->assertForbidden();

        $this->assertSame('The rate is 250', $message->fresh()->body);
    }

    /**
     * A removed message keeps its place and says so. The words go; the fact
     * that something was said and taken back does not.
     */
    public function test_removing_a_message_clears_it_but_leaves_the_row(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);
        $message = $thread->messages()->create(['user_id' => $client->id, 'body' => 'Sent by mistake']);

        $this->actingAs($client)
            ->delete(route('messages.remove', [
                'current_team' => $client->currentTeam,
                'conversation' => $thread,
                'message' => $message,
            ]))
            ->assertSessionHasNoErrors();

        $message->refresh();

        $this->assertNull($message->body);
        $this->assertTrue($message->isRemoved());
        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    public function test_a_removed_message_can_not_then_be_edited(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);
        $message = $thread->messages()->create([
            'user_id' => $client->id,
            'body' => null,
        ]);
        $message->forceFill(['removed_at' => now()])->save();

        $this->actingAs($client)
            ->patch(route('messages.edit', [
                'current_team' => $client->currentTeam,
                'conversation' => $thread,
                'message' => $message,
            ]), ['body' => 'Putting it back'])
            ->assertForbidden();
    }

    public function test_either_side_can_react_and_pressing_again_takes_it_back(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);
        $message = $thread->messages()->create(['user_id' => $client->id, 'body' => 'Turnover is Friday']);

        $url = route('messages.react', [
            'current_team' => $student->currentTeam,
            'conversation' => $thread,
            'message' => $message,
        ]);

        // Reacting is a reply, not a rewrite, so the recipient may do it.
        $this->actingAs($student)->post($url, ['emoji' => '❤️'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $student->id,
            'emoji' => '❤️',
        ]);

        $this->actingAs($student)->post($url, ['emoji' => '❤️']);
        $this->assertDatabaseMissing('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_an_emoji_outside_the_set_is_rejected(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);
        $message = $thread->messages()->create(['user_id' => $client->id, 'body' => 'Hello']);

        $this->actingAs($student)
            ->from(route('messages.show', ['current_team' => $student->currentTeam, 'conversation' => $thread]))
            ->post(route('messages.react', [
                'current_team' => $student->currentTeam,
                'conversation' => $thread,
                'message' => $message,
            ]), ['emoji' => 'not an emoji'])
            ->assertSessionHasErrors('emoji');
    }

    public function test_a_picture_can_be_sent_with_no_words(): void
    {
        Storage::fake('public');

        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);

        $this->actingAs($client)
            ->post(route('messages.send', [
                'current_team' => $client->currentTeam,
                'conversation' => $thread,
            ]), ['image' => UploadedFile::fake()->image('mockup.png')])
            ->assertSessionHasNoErrors();

        $message = $thread->messages()->latest('id')->first();

        $this->assertNull($message->body);
        $this->assertNotNull($message->attachment_path);
        Storage::disk('public')->assertExists($message->attachment_path);
    }

    public function test_a_message_with_neither_words_nor_a_picture_is_rejected(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);

        $this->actingAs($client)
            ->from(route('messages.show', ['current_team' => $client->currentTeam, 'conversation' => $thread]))
            ->post(route('messages.send', [
                'current_team' => $client->currentTeam,
                'conversation' => $thread,
            ]), [])
            ->assertSessionHasErrors('body');
    }

    /**
     * Removing takes the picture off disk too — leaving the file served at a
     * guessable URL would mean the message was never really taken back.
     */
    public function test_removing_a_message_deletes_its_picture(): void
    {
        Storage::fake('public');

        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);

        $this->actingAs($client)->post(route('messages.send', [
            'current_team' => $client->currentTeam,
            'conversation' => $thread,
        ]), ['image' => UploadedFile::fake()->image('mockup.png')]);

        $message = $thread->messages()->latest('id')->first();
        $path = $message->attachment_path;

        $this->actingAs($client)->delete(route('messages.remove', [
            'current_team' => $client->currentTeam,
            'conversation' => $thread,
            'message' => $message,
        ]));

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($message->fresh()->attachment_path);
    }

    /**
     * A message id borrowed from another thread must not resolve against the
     * conversation in the URL.
     */
    public function test_a_message_from_another_thread_is_not_found(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);

        [$otherClient, $otherStudent, $otherProject] = $this->pair(applied: true);
        $otherThread = $this->thread($otherProject, $otherStudent);
        $strayMessage = $otherThread->messages()->create([
            'user_id' => $otherClient->id,
            'body' => 'Said somewhere else',
        ]);

        $this->actingAs($client)
            ->delete(route('messages.remove', [
                'current_team' => $client->currentTeam,
                'conversation' => $thread,
                'message' => $strayMessage,
            ]))
            ->assertNotFound();

        $this->assertFalse($strayMessage->fresh()->isRemoved());
    }

    public function test_sending_broadcasts_to_the_threads_private_channel(): void
    {
        Event::fake([MessageSent::class]);

        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);

        $this->actingAs($client)->post(route('messages.send', [
            'current_team' => $client->currentTeam,
            'conversation' => $thread,
        ]), ['body' => 'Arriving without a page refresh']);

        Event::assertDispatched(
            MessageSent::class,
            fn (MessageSent $event): bool => $event->message->conversation_id === $thread->id,
        );
    }

    /**
     * Editing, removing and reacting all change what the other side is looking
     * at, so each has to reach them the same way a new message does.
     */
    public function test_editing_removing_and_reacting_all_broadcast(): void
    {
        Event::fake([MessageSent::class]);

        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);
        $message = $thread->messages()->create(['user_id' => $client->id, 'body' => 'First']);

        // Each side reaches the thread through its own team, so the student's
        // request cannot borrow the client's slug — EnsureTeamMembership 403s.
        $asClient = [
            'current_team' => $client->currentTeam,
            'conversation' => $thread,
            'message' => $message,
        ];
        $asStudent = [...$asClient, 'current_team' => $student->currentTeam];

        $this->actingAs($client)->patch(route('messages.edit', $asClient), ['body' => 'Second'])->assertSessionHasNoErrors();
        $this->actingAs($student)->post(route('messages.react', $asStudent), ['emoji' => '👍'])->assertSessionHasNoErrors();
        $this->actingAs($client)->delete(route('messages.remove', $asClient))->assertSessionHasNoErrors();

        Event::assertDispatchedTimes(MessageSent::class, 3);
    }

    /**
     * A socket is another door into the same thread, so it takes the same key:
     * a participant may listen, a stranger may not.
     */
    public function test_only_a_participant_may_listen_to_a_threads_channel(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);
        $stranger = User::factory()->student()->create();

        $channel = new ConversationChannel;

        $this->assertTrue($channel->join($client, $thread->id));
        $this->assertTrue($channel->join($student, $thread->id));
        $this->assertFalse($channel->join($stranger, $thread->id));

        // A thread that does not exist is not a thread anyone may listen to.
        $this->assertFalse($channel->join($client, 999999));
    }

    /*
     * ---------------------------------------------------------------------
     * A broadcaster that is not there
     * ---------------------------------------------------------------------
     *
     * MessageSent is ShouldBroadcastNow, so it goes out on the request rather
     * than through a worker. Reverb is a separate process someone has to
     * start, and when it was not listening the broadcast threw after the
     * message had already been committed — so the sender got an error page for
     * a message that had in fact been sent. The live update is a courtesy on
     * top of a write that already succeeded, and is treated as one.
     */

    public function test_a_message_still_sends_when_the_broadcaster_is_unreachable(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $conversation = $this->thread($project, $student);

        $this->breakTheBroadcaster();

        $this->actingAs($client)
            ->from(route('messages.show', [
                'current_team' => $client->currentTeam,
                'conversation' => $conversation,
            ]))
            ->post(route('messages.send', [
                'current_team' => $client->currentTeam,
                'conversation' => $conversation,
            ]), ['body' => 'Reverb is not running, but this must still arrive.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'user_id' => $client->id,
            'body' => 'Reverb is not running, but this must still arrive.',
        ]);
    }

    public function test_editing_reacting_and_removing_survive_an_unreachable_broadcaster(): void
    {
        [$client, $student, $project] = $this->pair(applied: true);
        $thread = $this->thread($project, $student);
        $message = $thread->messages()->create(['user_id' => $client->id, 'body' => 'Teh meeting is at 3']);

        $this->breakTheBroadcaster();

        /*
         * Each side walks in through its own team's URL. Borrowing the other
         * side's would be turned away by EnsureTeamMembership long before the
         * broadcaster was reached, and the test would prove nothing.
         */
        $asClient = ['current_team' => $client->currentTeam, 'conversation' => $thread, 'message' => $message];
        $asStudent = ['current_team' => $student->currentTeam, 'conversation' => $thread, 'message' => $message];

        $this->actingAs($client)
            ->patch(route('messages.edit', $asClient), ['body' => 'The meeting is at 3'])
            ->assertSessionHasNoErrors();

        $this->assertSame('The meeting is at 3', $message->fresh()->body);

        $this->actingAs($student)
            ->post(route('messages.react', $asStudent), ['emoji' => MessageReaction::ALLOWED[0]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $message->reactions()->count());

        $this->actingAs($client)
            ->delete(route('messages.remove', $asClient))
            ->assertSessionHasNoErrors();

        $this->assertTrue($message->fresh()->isRemoved());
    }

    /**
     * Point broadcasting at a driver that cannot deliver, the way an unstarted
     * Reverb behaves: the write succeeds and the announcement throws.
     */
    private function breakTheBroadcaster(): void
    {
        Broadcast::extend('unreachable', fn (): Broadcaster => new class implements Broadcaster
        {
            public function auth($request)
            {
                return true;
            }

            public function validAuthenticationResponse($request, $result)
            {
                return $result;
            }

            public function broadcast(array $channels, $event, array $payload = []): void
            {
                throw new BroadcastException(
                    'Pusher error: cURL error 7: Failed to connect to localhost port 8080.',
                );
            }
        });

        config([
            'broadcasting.default' => 'unreachable',
            'broadcasting.connections.unreachable' => ['driver' => 'unreachable'],
        ]);
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

    /**
     * The header's unread badge costs the same whether you have two threads or
     * ten.
     *
     * It is shared with every screen, so the query behind it runs on every
     * full page load in the application. It read the last message off each
     * thread one at a time, which made the cost of drawing any page at all
     * grow with the number of conversations the signed-in person was in.
     *
     * Pinned as "does not grow" rather than as an exact number, because the
     * count that matters is per thread, and the rest of the page's queries are
     * nobody's business here.
     */
    public function test_the_unread_badge_does_not_query_once_per_thread(): void
    {
        $this->assertSame(
            $this->messageQueriesLoadingTheInbox(threads: 2),
            $this->messageQueriesLoadingTheInbox(threads: 8),
        );
    }

    /**
     * Stand up a client with the given number of threads, load a page, and
     * count how many queries went to the messages table.
     *
     * A thread is unique on its posting and its student, so the threads are
     * made by putting that many different students on the one posting.
     */
    private function messageQueriesLoadingTheInbox(int $threads): int
    {
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
        $project = Project::factory()->create(['team_id' => $client->current_team_id]);

        foreach (range(1, $threads) as $ignored) {
            $student = User::factory()->student()->approved()->create();

            $thread = $this->thread($project, $student);

            // From the other side, so it actually counts as unread.
            $thread->messages()->create(['user_id' => $student->id, 'body' => 'Any word on the brief?']);
        }

        $queries = 0;

        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_contains($query->sql, 'from "messages"')) {
                $queries++;
            }
        });

        $this->actingAs($client->fresh())
            ->get(route('messages.index', ['current_team' => $client->fresh()->currentTeam]))
            ->assertOk();

        return $queries;
    }
}
