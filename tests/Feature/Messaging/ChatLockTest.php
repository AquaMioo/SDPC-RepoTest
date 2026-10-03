<?php

namespace Tests\Feature\Messaging;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Nobody chats until both sides have accepted each other.
 *
 * The thread opens with the introduction, so it is in both inboxes, but it is
 * read-only — no messages, no reactions, no calls — until the application
 * behind it is Accepted: by the student for a client's invitation, by the
 * client for a student's application. Then it opens for both.
 *
 * Routes are pinned with an explicit current_team; see .ai/rules/feature.md.
 */
class ChatLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'agora.enabled' => true,
            'agora.app_id' => '0123456789abcdef0123456789abcdef',
            'agora.app_certificate' => 'fedcba9876543210fedcba9876543210',
        ]);
    }

    public function test_an_invitation_keeps_the_thread_locked_until_the_student_accepts(): void
    {
        [$client, $student, $project] = $this->pair();

        /* The client invites: the thread opens, read-only. */
        $this->actingAs($client)
            ->post(route('projects.invitations.store', ['current_team' => $client->currentTeam, 'project' => $project]), ['user_id' => $student->id])
            ->assertSessionHasNoErrors();

        $thread = Conversation::query()->firstOrFail();
        $invitation = Application::query()->firstOrFail();

        $this->assertLocked($client, $thread, 'Chat opens once the student accepts your invitation.');
        $this->assertLocked($student, $thread, 'Accept the invitation to start chatting with this client.');

        /* The student accepts: both may write. */
        $this->actingAs($student)
            ->post(route('student.applications.accept', ['current_team' => $student->currentTeam, 'application' => $invitation]))
            ->assertSessionHasNoErrors();

        $this->assertOpen($client, $thread);
        $this->assertOpen($student, $thread);
        $this->assertSame(2, $thread->messages()->count());
    }

    public function test_an_application_keeps_the_thread_locked_until_the_client_accepts(): void
    {
        [$client, $student, $project] = $this->pair();

        $application = Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Pending,
            'source' => ApplicationSource::Applied,
        ]);

        /* Either side pressing Message opens the thread, still read-only. */
        $this->actingAs($student)
            ->post(route('messages.store', ['current_team' => $student->currentTeam]), ['project_id' => $project->id, 'user_id' => $student->id])
            ->assertRedirect();

        $thread = Conversation::query()->firstOrFail();

        $this->assertLocked($student, $thread, 'Chat opens once the client accepts your application.');
        $this->assertLocked($client, $thread, 'Chat opens once you accept this application.');

        /* Shortlisting is not accepting. */
        $application->update(['status' => ApplicationStatus::Shortlisted]);
        $this->assertLocked($student, $thread, 'Chat opens once the client accepts your application.');

        $this->actingAs($client)
            ->patch(route('applications.update', ['current_team' => $client->currentTeam, 'application' => $application]), ['status' => 'accepted'])
            ->assertSessionHasNoErrors();

        $this->assertOpen($student, $thread);
        $this->assertOpen($client, $thread);
    }

    public function test_a_rejected_or_withdrawn_application_never_opens_the_thread(): void
    {
        [$client, $student, $project] = $this->pair();

        foreach ([ApplicationStatus::Rejected, ApplicationStatus::Withdrawn] as $status) {
            Application::query()->updateOrCreate(
                ['project_id' => $project->id, 'user_id' => $student->id],
                ['status' => $status],
            );

            $thread = Conversation::query()->firstOrCreate(['project_id' => $project->id, 'user_id' => $student->id]);

            $this->assertLocked($client, $thread);
            $this->assertLocked($student, $thread);
        }
    }

    /**
     * A thread somebody wrote in before the rule (or that is gone) stays
     * readable, cannot be reacted to while locked, and is there to open once
     * the invitation is accepted.
     */
    public function test_older_lines_stay_readable_and_accepting_creates_a_missing_thread(): void
    {
        [$client, $student, $project] = $this->pair();

        $application = Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Pending,
            'source' => ApplicationSource::Invited,
        ]);

        $thread = Conversation::create(['project_id' => $project->id, 'user_id' => $student->id]);
        $old = Message::query()->create(['conversation_id' => $thread->id, 'user_id' => $client->id, 'body' => 'Written before the lock.']);

        $this->actingAs($student)
            ->get(route('messages.show', ['current_team' => $student->currentTeam, 'conversation' => $thread]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('active.messages.0.body', 'Written before the lock.')
                ->where('active.chatLock', 'Accept the invitation to start chatting with this client.'));

        $this->actingAs($student)
            ->post(route('messages.react', ['current_team' => $student->currentTeam, 'conversation' => $thread, 'message' => $old]), ['emoji' => '👍'])
            ->assertForbidden();

        /* The thread is gone; accepting the invitation brings it back, open. */
        $thread->messages()->delete();
        $thread->delete();

        $this->actingAs($student)
            ->post(route('student.applications.accept', ['current_team' => $student->currentTeam, 'application' => $application]))
            ->assertSessionHasNoErrors();

        $reopened = Conversation::query()->where('project_id', $project->id)->where('user_id', $student->id)->firstOrFail();
        $this->assertOpen($student, $reopened);
    }

    /**
     * Assert nobody on that side can write, react or call in the thread, and
     * the screen says why.
     */
    private function assertLocked(User $user, Conversation $thread, ?string $reason = null): void
    {
        $before = $thread->messages()->count();

        $this->actingAs($user)
            ->from(route('messages.show', ['current_team' => $user->currentTeam, 'conversation' => $thread]))
            ->post(route('messages.send', ['current_team' => $user->currentTeam, 'conversation' => $thread]), ['body' => 'Hello?'])
            ->assertSessionHasErrors($reason === null ? 'body' : ['body' => $reason]);

        $this->actingAs($user)
            ->postJson(route('meetings.store', ['current_team' => $user->currentTeam, 'conversation' => $thread]))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('messages.show', ['current_team' => $user->currentTeam, 'conversation' => $thread]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $reason === null
                ? $page->whereNot('active.chatLock', null)
                : $page->where('active.chatLock', $reason));

        $this->assertSame($before, $thread->messages()->count());
        $this->assertSame(0, $thread->meetings()->count());
    }

    /**
     * Assert the user can write in the thread and the screen offers it.
     */
    private function assertOpen(User $user, Conversation $thread): void
    {
        $this->actingAs($user)
            ->from(route('messages.show', ['current_team' => $user->currentTeam, 'conversation' => $thread]))
            ->post(route('messages.send', ['current_team' => $user->currentTeam, 'conversation' => $thread]), ['body' => "Hello from {$user->name}"])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('messages.show', ['current_team' => $user->currentTeam, 'conversation' => $thread]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('active.chatLock', null));
    }

    /**
     * A verified business with an open posting, and a verified student.
     *
     * @return array{0: User, 1: User, 2: Project}
     */
    private function pair(): array
    {
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
        $student = User::factory()->student()->approved()->create();

        $project = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'created_by' => $client->id,
            'status' => ProjectStatus::Open,
        ]);

        return [$client->fresh(), $student->fresh(), $project];
    }
}
