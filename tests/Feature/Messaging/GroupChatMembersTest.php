<?php

namespace Tests\Feature\Messaging;

use App\Enums\ApplicationStatus;
use App\Enums\TeamRole;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Messaging\NewMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * A group chat is invite-only.
 *
 * Being on the student's team used to be enough to read every thread the
 * team was attached to, including for somebody who joined the team later.
 * Now the team's creator invites teammates one by one and can take them out;
 * the thread's student and the business are always in.
 */
class GroupChatMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_teammate_who_was_not_invited_cannot_read_the_thread(): void
    {
        [, $creator, $team, $thread] = $this->creatorThread();
        $mate = $this->teammate($team);

        $this->assertFalse($thread->fresh()->isParticipant($mate));

        $this->actingAs($mate)
            ->get(route('messages.show', ['current_team' => $team, 'conversation' => $thread]))
            ->assertForbidden();

        $this->actingAs($mate)
            ->get(route('messages.index', ['current_team' => $team]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('threads', 0));

        /* And cannot join its calls or send to it either. */
        $this->actingAs($mate)
            ->post(route('messages.send', ['current_team' => $team, 'conversation' => $thread]), ['body' => 'Hello?'])
            ->assertForbidden();
    }

    public function test_an_invited_teammate_reads_and_writes_the_thread(): void
    {
        [, $creator, $team, $thread] = $this->creatorThread();
        $mate = $this->teammate($team);

        $this->invite($creator, $team, $thread, $mate)->assertSessionHasNoErrors();

        $this->actingAs($mate)
            ->get(route('messages.index', ['current_team' => $team]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('threads', 1));

        $this->actingAs($mate)
            ->post(route('messages.send', ['current_team' => $team, 'conversation' => $thread]), ['body' => 'On it.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $thread->messages()->count());
    }

    public function test_only_the_team_creator_may_invite(): void
    {
        [, $creator, $team, $thread] = $this->creatorThread();
        $mate = $this->teammate($team);
        $other = $this->teammate($team);

        $thread->members()->attach($mate);

        /* Already in the chat, but not the creator. */
        $this->invite($mate, $team, $thread, $other)->assertForbidden();

        $this->assertFalse($thread->fresh()->isParticipant($other));
    }

    public function test_only_teammates_not_yet_in_the_chat_can_be_invited(): void
    {
        [, $creator, $team, $thread] = $this->creatorThread();
        $mate = $this->teammate($team);
        $outsider = User::factory()->student()->approved()->create();

        $expected = 'Only teammates on '.$team->name.' who are not in this chat yet can be invited.';

        $this->invite($creator, $team, $thread, $outsider)->assertSessionHasErrors(['user_id' => $expected]);
        $this->invite($creator, $team, $thread, $creator)->assertSessionHasErrors(['user_id' => $expected]);

        $this->invite($creator, $team, $thread, $mate)->assertSessionHasNoErrors();
        $this->invite($creator, $team, $thread, $mate)
            ->assertSessionHasErrors(['user_id' => $mate->name.' is already in this chat.']);

        $this->assertSame([$mate->id], $thread->members()->pluck('users.id')->all());
    }

    public function test_the_creator_can_take_a_teammate_out_again(): void
    {
        [, $creator, $team, $thread] = $this->creatorThread();
        $mate = $this->teammate($team);
        $thread->members()->attach($mate);

        $this->actingAs($creator)
            ->delete(route('messages.members.destroy', [
                'current_team' => $team,
                'conversation' => $thread,
                'member' => $mate,
            ]))
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => $mate->name.' was removed from this chat.']);

        $this->assertFalse($thread->fresh()->isParticipant($mate));

        /* Somebody not in the chat cannot be taken out of it. */
        $this->actingAs($creator)
            ->delete(route('messages.members.destroy', [
                'current_team' => $team,
                'conversation' => $thread,
                'member' => $mate,
            ]))
            ->assertNotFound();
    }

    public function test_the_threads_student_and_the_creator_cannot_be_taken_out(): void
    {
        [, $creator, $team, $thread] = $this->creatorThread();

        $this->actingAs($creator)
            ->delete(route('messages.members.destroy', [
                'current_team' => $team,
                'conversation' => $thread,
                'member' => $creator,
            ]))
            ->assertSessionHasErrors(['member' => $creator->name.' cannot be removed from this chat.']);

        $this->assertTrue($thread->fresh()->isParticipant($creator));
    }

    public function test_an_invited_teammate_cannot_take_anybody_out(): void
    {
        [, $creator, $team, $thread] = $this->creatorThread();
        $mate = $this->teammate($team);
        $other = $this->teammate($team);
        $thread->members()->attach([$mate->id, $other->id]);

        $this->actingAs($mate)
            ->delete(route('messages.members.destroy', [
                'current_team' => $team,
                'conversation' => $thread,
                'member' => $other,
            ]))
            ->assertForbidden();

        $this->assertTrue($thread->fresh()->isParticipant($other));
    }

    /**
     * The panel: the creator sees who can still be invited, everybody else
     * only sees who is in.
     */
    public function test_the_panel_shows_the_creator_whom_they_can_invite(): void
    {
        [$client, $creator, $team, $thread] = $this->creatorThread();
        $mate = $this->teammate($team);
        $waiting = $this->teammate($team);
        $thread->members()->attach($mate);

        $this->actingAs($creator)
            ->get(route('messages.show', ['current_team' => $team, 'conversation' => $thread]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('group.isGroup', true)
                ->where('group.canManage', true)
                ->where('group.teamName', $team->name)
                ->where('group.people', [
                    ['id' => $creator->id, 'name' => $creator->name, 'isCreator' => true, 'isThreadOwner' => true],
                    ['id' => $mate->id, 'name' => $mate->name, 'isCreator' => false, 'isThreadOwner' => false],
                ])
                ->where('group.invitable', [['id' => $waiting->id, 'name' => $waiting->name]]));

        foreach ([$mate, $client] as $viewer) {
            $this->actingAs($viewer)
                ->get(route('messages.show', ['current_team' => $viewer->currentTeam, 'conversation' => $thread]))
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('group.canManage', false)
                    ->has('group.people', 2)
                    ->where('group.invitable', []));
        }
    }

    /**
     * Joining the team is not joining its conversations.
     */
    public function test_joining_the_team_later_does_not_add_you_to_its_chats(): void
    {
        [, $creator, $team, $thread] = $this->creatorThread();
        $mate = $this->teammate($team);
        $thread->members()->attach($mate);

        $latecomer = $this->teammate($team);

        $this->actingAs($creator)
            ->get(route('messages.show', ['current_team' => $team, 'conversation' => $thread]))
            ->assertOk();

        $this->assertFalse($thread->fresh()->isParticipant($latecomer));
    }

    /**
     * A member's own conversations with clients stay theirs. Only the
     * creator's threads pick up the team.
     */
    public function test_a_members_own_thread_is_not_handed_to_the_team(): void
    {
        [, $creator, $team] = $this->creatorThread();
        $mate = $this->teammate($team);
        [, $ownThread] = $this->threadFor($mate);

        $this->actingAs($mate)
            ->get(route('messages.show', ['current_team' => $team, 'conversation' => $ownThread]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('group.canManage', false)
                ->where('group.people', []));

        $this->assertNull($ownThread->fresh()->student_team_id);
        $this->assertFalse($ownThread->fresh()->isParticipant($creator));
    }

    /**
     * An invited teammate is on the student's side of the thread: they see
     * the business's name as its title, and reading it does not clear the
     * client's unread badge.
     */
    public function test_an_invited_teammate_is_on_the_students_side(): void
    {
        [$client, $creator, $team, $thread, $project] = $this->creatorThread();
        $mate = $this->teammate($team);
        $thread->members()->attach($mate);

        $thread->messages()->create(['user_id' => $creator->id, 'body' => 'Draft attached.']);

        $this->actingAs($mate)
            ->get(route('messages.show', ['current_team' => $team, 'conversation' => $thread]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('active.title', $project->team->clientProfile?->business_name ?? $project->team->name));

        $thread->refresh();

        $this->assertNull($thread->client_read_message_id);
        $this->assertTrue($thread->isUnreadFor($client));
        $this->assertNotNull($thread->student_read_message_id);
    }

    /**
     * A teammate writing reaches the business; the business writing reaches
     * every invited teammate as well as the thread's student.
     */
    public function test_messages_notify_the_right_side_of_a_group_chat(): void
    {
        [$client, $creator, $team, $thread] = $this->creatorThread();
        $mate = $this->teammate($team);
        $uninvited = $this->teammate($team);
        $thread->members()->attach($mate);

        $this->actingAs($mate)
            ->post(route('messages.send', ['current_team' => $team, 'conversation' => $thread]), ['body' => 'Mockups are up.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $client->notifications()->where('type', NewMessage::class)->count());
        $this->assertSame(0, $creator->notifications()->where('type', NewMessage::class)->count());

        /* The business reads it, then replies into a quiet thread. */
        $this->actingAs($client)
            ->get(route('messages.show', ['current_team' => $client->currentTeam, 'conversation' => $thread]))
            ->assertOk();

        $this->actingAs($client)
            ->post(route('messages.send', ['current_team' => $client->currentTeam, 'conversation' => $thread]), ['body' => 'Looks good.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $creator->notifications()->where('type', NewMessage::class)->count());
        $this->assertSame(1, $mate->notifications()->where('type', NewMessage::class)->count());
        $this->assertSame(0, $uninvited->notifications()->where('type', NewMessage::class)->count());
    }

    public function test_leaving_the_team_takes_you_out_of_its_chats(): void
    {
        [, $creator, $team, $thread] = $this->creatorThread();
        $mate = $this->teammate($team);
        $mate->switchTeam($team);
        $thread->members()->attach($mate);

        $this->actingAs($mate)->delete(route('teams.leave', $team))->assertRedirect();

        $this->assertSame(0, $thread->members()->count());
        $this->assertFalse($thread->fresh()->isParticipant($mate));
    }

    /**
     * Existing group chats keep everybody who was in them when invitations
     * began: the migration writes the team in as it stood.
     */
    public function test_existing_group_chats_keep_their_members(): void
    {
        [, $creator, $team, $thread] = $this->creatorThread();
        $mate = $this->teammate($team);

        $migration = require database_path('migrations/2026_09_16_180648_create_conversation_members_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame([$mate->id], $thread->members()->pluck('users.id')->all());
        $this->assertTrue($thread->fresh()->isParticipant($mate));
    }

    /**
     * A client, and a student who created a team, with a thread between them
     * that has picked up the team.
     *
     * @return array{0: User, 1: User, 2: Team, 3: Conversation, 4: Project}
     */
    private function creatorThread(): array
    {
        $creator = User::factory()->student()->approved()->create();
        $team = $creator->currentTeam;
        $team->forceFill(['is_personal' => false])->save();

        [$client, $thread, $project] = $this->threadFor($creator);

        $thread->forceFill(['student_team_id' => $team->id])->save();

        return [$client, $creator->fresh(), $team->fresh(), $thread->fresh(), $project];
    }

    /**
     * A client and a thread with the given student, linked by an application.
     *
     * @return array{0: User, 1: Conversation, 2: Project}
     */
    private function threadFor(User $student): array
    {
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
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

        return [$client->fresh(), $thread, $project];
    }

    /**
     * A student who has joined the given team, and is on no other.
     */
    private function teammate(Team $team): User
    {
        $student = User::factory()->student()->approved()->create();
        $ownTeam = $student->currentTeam;

        $team->members()->attach($student, ['role' => TeamRole::LeadProgrammer->value]);
        $student->switchTeam($team);
        $ownTeam->memberships()->delete();
        $ownTeam->delete();

        return $student->fresh();
    }

    private function invite(User $as, Team $team, Conversation $thread, User $member): TestResponse
    {
        return $this->actingAs($as)
            ->post(route('messages.members.store', [
                'current_team' => $team,
                'conversation' => $thread,
            ]), ['user_id' => $member->id]);
    }
}
