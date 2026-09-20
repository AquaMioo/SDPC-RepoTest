<?php

namespace Tests\Feature\Messaging;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The two things the hover actions beside a bubble can do that the thread
 * could not do before: answer one message in particular, and take one line out
 * of your own view without touching anybody else's.
 */
class MessageRepliesAndHidingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reply_records_the_message_it_answers(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        $asked = $thread->messages()->create([
            'user_id' => $client->id,
            'body' => 'Are you free on Thursday?',
        ]);

        $this->actingAs($student)
            ->post(route('messages.send', [
                'current_team' => $student->currentTeam,
                'conversation' => $thread,
            ]), [
                'body' => 'Thursday works',
                'reply_to_message_id' => $asked->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('messages', [
            'body' => 'Thursday works',
            'reply_to_message_id' => $asked->id,
        ]);
    }

    /**
     * The quoted line travels with the message, so the thread can draw it
     * without the browser hunting back through the conversation for it.
     */
    public function test_the_thread_carries_the_quoted_line_above_a_reply(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        $asked = $thread->messages()->create([
            'user_id' => $client->id,
            'body' => 'Are you free on Thursday?',
        ]);

        $thread->messages()->create([
            'user_id' => $student->id,
            'body' => 'Thursday works',
            'reply_to_message_id' => $asked->id,
        ]);

        $this->actingAs($student)
            ->get(route('messages.show', [
                'current_team' => $student->currentTeam,
                'conversation' => $thread,
            ]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('active.messages.1.replyTo.author', $client->name)
                ->where('active.messages.1.replyTo.excerpt', 'Are you free on Thursday?')
                ->where('active.messages.0.replyTo', null)
            );
    }

    /**
     * A reply may only quote its own thread.
     *
     * Without the scope on the rule any id would pass, and a reply would carry
     * a line out of a conversation the sender is not even in.
     */
    public function test_a_reply_may_not_quote_a_message_from_another_thread(): void
    {
        [, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        [$otherClient, $otherStudent, $otherProject] = $this->pair();
        $elsewhere = $this->thread($otherProject, $otherStudent);

        $private = $elsewhere->messages()->create([
            'user_id' => $otherClient->id,
            'body' => 'Our budget is tight this quarter',
        ]);

        $this->actingAs($student)
            ->post(route('messages.send', [
                'current_team' => $student->currentTeam,
                'conversation' => $thread,
            ]), [
                'body' => 'Quoting something I should not see',
                'reply_to_message_id' => $private->id,
            ])
            ->assertSessionHasErrors('reply_to_message_id');

        $this->assertDatabaseMissing('messages', [
            'body' => 'Quoting something I should not see',
        ]);
    }

    /**
     * "Remove for you" is not "remove for everyone".
     *
     * The row stays exactly as it was for the other side of the thread — only
     * the person who asked stops being sent it.
     */
    public function test_removing_a_message_for_yourself_leaves_it_for_the_other_side(): void
    {
        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        $message = $thread->messages()->create([
            'user_id' => $student->id,
            'body' => 'Thursday works',
        ]);

        $this->actingAs($client)
            ->post(route('messages.hide', [
                'current_team' => $client->currentTeam,
                'conversation' => $thread,
                'message' => $message,
            ]))
            ->assertSessionHasNoErrors();

        /* Gone from the view of the person who asked. */
        $this->actingAs($client)
            ->get(route('messages.show', [
                'current_team' => $client->currentTeam,
                'conversation' => $thread,
            ]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->count('active.messages', 0)
            );

        /* Still there for the sender, and not marked as taken back. */
        $this->actingAs($student)
            ->get(route('messages.show', [
                'current_team' => $student->currentTeam,
                'conversation' => $thread,
            ]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->count('active.messages', 1)
                ->where('active.messages.0.body', 'Thursday works')
                ->where('active.messages.0.isRemoved', false)
            );

        $this->assertNull($message->fresh()->removed_at);
    }

    public function test_a_stranger_can_not_hide_a_message_in_somebody_elses_thread(): void
    {
        [, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        $message = $thread->messages()->create([
            'user_id' => $student->id,
            'body' => 'Thursday works',
        ]);

        $stranger = User::factory()->student()->approved()->create();

        $this->actingAs($stranger)
            ->post(route('messages.hide', [
                'current_team' => $stranger->currentTeam,
                'conversation' => $thread,
                'message' => $message,
            ]))
            ->assertForbidden();

        $this->assertDatabaseCount('message_hides', 0);
    }

    /**
     * Every face in the thread comes from User::avatarUrl().
     *
     * Read off users.avatar instead and a person who uploaded a picture keeps
     * showing whichever one Google last handed over.
     */
    public function test_the_thread_draws_an_author_from_their_uploaded_picture(): void
    {
        Storage::fake('public');

        [$client, $student, $project] = $this->pair();
        $thread = $this->thread($project, $student);

        $student->forceFill([
            'avatar' => 'https://lh3.googleusercontent.com/stale-google-photo',
            'avatar_path' => UploadedFile::fake()
                ->image('me.jpg')
                ->store('avatars/'.$student->id, 'public'),
        ])->save();

        $thread->messages()->create([
            'user_id' => $student->id,
            'body' => 'Thursday works',
        ]);

        $this->actingAs($client)
            ->get(route('messages.show', [
                'current_team' => $client->currentTeam,
                'conversation' => $thread,
            ]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where(
                    'active.messages.0.authorAvatarUrl',
                    Storage::disk('public')->url($student->fresh()->avatar_path),
                )
            );
    }

    /**
     * A client and a student with an application between them, which is what
     * makes a thread possible at all.
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
}
