<?php

namespace Tests\Feature\Client;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Enums\VerificationStatus;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\Client\ProjectInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Recruit is only useful if a client can act on what they find. Browsing to a
 * student, inviting them onto a posting, and then messaging them is the whole
 * outreach path — the mirror of a student applying and messaging back.
 */
class RecruitOutreachTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_profile_offers_the_postings_a_student_is_not_on(): void
    {
        [$client, $student] = $this->pair();

        Project::factory()->create([
            'team_id' => $client->current_team_id,
            'title' => 'Inventory System',
            'status' => ProjectStatus::Open,
        ]);

        $this->actingAs($client)
            ->get(route('students.show', [
                'current_team' => $client->currentTeam,
                'user' => $student,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('invitableProjects', 1)
                ->where('invitableProjects.0.title', 'Inventory System')
                ->where('canInvite', true)
                ->has('existingApplications', 0));
    }

    public function test_a_posting_the_student_is_already_on_is_not_offered_twice(): void
    {
        [$client, $student] = $this->pair();

        $project = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'status' => ProjectStatus::Open,
        ]);

        Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Pending,
        ]);

        $this->actingAs($client)
            ->get(route('students.show', [
                'current_team' => $client->currentTeam,
                'user' => $student,
            ]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('invitableProjects', 0)
                ->has('existingApplications', 1)
                ->where('existingApplications.0.projectId', $project->id));
    }

    public function test_another_businesses_postings_are_never_offered(): void
    {
        [$client, $student] = $this->pair();

        Project::factory()->create(['status' => ProjectStatus::Open]);

        $this->actingAs($client)
            ->get(route('students.show', [
                'current_team' => $client->currentTeam,
                'user' => $student,
            ]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('invitableProjects', 0));
    }

    public function test_an_unverified_business_is_told_it_can_not_invite(): void
    {
        [$client, $student] = $this->pair(verified: false);

        Project::factory()->create([
            'team_id' => $client->current_team_id,
            'status' => ProjectStatus::Open,
        ]);

        $this->actingAs($client)
            ->get(route('students.show', [
                'current_team' => $client->currentTeam,
                'user' => $student,
            ]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canInvite', false));
    }

    public function test_inviting_puts_the_student_on_the_posting(): void
    {
        [$client, $student] = $this->pair();

        $project = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'status' => ProjectStatus::Open,
        ]);

        $this->actingAs($client)
            ->from(route('students.show', [
                'current_team' => $client->currentTeam,
                'user' => $student,
            ]))
            ->post(route('projects.invitations.store', [
                'current_team' => $client->currentTeam,
                'project' => $project,
            ]), ['user_id' => $student->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('applications', [
            'project_id' => $project->id,
            'user_id' => $student->id,
            'source' => ApplicationSource::Invited->value,
            'status' => ApplicationStatus::Pending->value,
        ]);
    }

    public function test_the_invited_student_is_told(): void
    {
        Notification::fake();

        [$client, $student] = $this->pair();

        $project = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'status' => ProjectStatus::Open,
        ]);

        $this->actingAs($client)
            ->from(route('students.show', [
                'current_team' => $client->currentTeam,
                'user' => $student,
            ]))
            ->post(route('projects.invitations.store', [
                'current_team' => $client->currentTeam,
                'project' => $project,
            ]), ['user_id' => $student->id])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo(
            $student,
            ProjectInvitation::class,
            fn (ProjectInvitation $notification): bool => $notification->application->project_id === $project->id,
        );
    }

    public function test_the_same_student_can_not_be_invited_to_one_posting_twice(): void
    {
        [$client, $student] = $this->pair();

        $project = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'status' => ProjectStatus::Open,
        ]);

        $url = route('projects.invitations.store', [
            'current_team' => $client->currentTeam,
            'project' => $project,
        ]);
        $from = route('students.show', [
            'current_team' => $client->currentTeam,
            'user' => $student,
        ]);

        $this->actingAs($client)->from($from)->post($url, ['user_id' => $student->id]);
        $this->actingAs($client)->from($from)->post($url, ['user_id' => $student->id])
            ->assertSessionHasErrors('user_id');

        $this->assertSame(1, Application::count());
    }

    public function test_an_unverified_business_can_not_invite(): void
    {
        [$client, $student] = $this->pair(verified: false);

        $project = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'status' => ProjectStatus::Open,
        ]);

        $this->actingAs($client)
            ->from(route('students.show', [
                'current_team' => $client->currentTeam,
                'user' => $student,
            ]))
            ->post(route('projects.invitations.store', [
                'current_team' => $client->currentTeam,
                'project' => $project,
            ]), ['user_id' => $student->id])
            ->assertSessionHasErrors('verification');

        $this->assertSame(0, Application::count());
    }

    public function test_the_invitation_is_what_opens_the_thread(): void
    {
        [$client, $student] = $this->pair();

        $project = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'status' => ProjectStatus::Open,
        ]);

        // Before the invitation there is no relationship to message across.
        $this->actingAs($client)
            ->post(route('messages.store', ['current_team' => $client->currentTeam]), [
                'project_id' => $project->id,
                'user_id' => $student->id,
            ])
            ->assertForbidden();

        $this->actingAs($client)
            ->from(route('students.show', [
                'current_team' => $client->currentTeam,
                'user' => $student,
            ]))
            ->post(route('projects.invitations.store', [
                'current_team' => $client->currentTeam,
                'project' => $project,
            ]), ['user_id' => $student->id]);

        // After it, the thread opens.
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

    public function test_the_invited_student_can_reply_in_that_thread(): void
    {
        [$client, $student] = $this->pair();

        $project = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'status' => ProjectStatus::Open,
        ]);

        Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'source' => ApplicationSource::Invited,
            'status' => ApplicationStatus::Pending,
        ]);

        $conversation = Conversation::create([
            'project_id' => $project->id,
            'user_id' => $student->id,
        ]);

        $this->actingAs($client)
            ->from(route('messages.index', ['current_team' => $client->currentTeam]))
            ->post(route('messages.send', [
                'current_team' => $client->currentTeam,
                'conversation' => $conversation,
            ]), ['body' => 'We saw your profile and would like you on this build.'])
            ->assertSessionHasNoErrors();

        $this->actingAs($student)
            ->from(route('messages.index', ['current_team' => $student->currentTeam]))
            ->post(route('messages.send', [
                'current_team' => $student->currentTeam,
                'conversation' => $conversation,
            ]), ['body' => 'Thanks — I am interested.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $conversation->messages()->count());
    }

    public function test_the_grid_can_message_a_student_already_on_a_posting(): void
    {
        [$client, $student] = $this->pair();

        $project = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'status' => ProjectStatus::Open,
        ]);

        Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'source' => ApplicationSource::Invited,
            'status' => ApplicationStatus::Pending,
        ]);

        $this->actingAs($client)
            ->get(route('recruit.index', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('students.data.0.messageableProjectId', $project->id));
    }

    public function test_the_grid_sends_an_uninvited_student_to_their_profile_instead(): void
    {
        [$client, $student] = $this->pair();

        Project::factory()->create([
            'team_id' => $client->current_team_id,
            'status' => ProjectStatus::Open,
        ]);

        // Null is what routes the card's Message button to the profile, where
        // the invitation lives. Posting a thread here would 403.
        $this->actingAs($client)
            ->get(route('recruit.index', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('students.data.0.id', $student->id)
                ->where('students.data.0.messageableProjectId', null));
    }

    public function test_another_businesses_application_does_not_open_the_grids_thread(): void
    {
        [$client, $student] = $this->pair();

        Application::factory()->create([
            'project_id' => Project::factory()->create()->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Pending,
        ]);

        $this->actingAs($client)
            ->get(route('recruit.index', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('students.data.0.messageableProjectId', null));
    }

    /**
     * A client with a business and a student with a findable profile.
     *
     * @return array{0: User, 1: User}
     */
    private function pair(bool $verified = true): array
    {
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();

        if (! $verified) {
            $client->currentTeam->clientProfile->forceFill([
                'verification_status' => VerificationStatus::Pending,
                'verified_at' => null,
            ])->save();
        }

        $student = User::factory()->student()->approved()->create();
        StudentProfile::factory()->for($student)->create();

        return [$client->fresh(), $student->fresh()];
    }
}
