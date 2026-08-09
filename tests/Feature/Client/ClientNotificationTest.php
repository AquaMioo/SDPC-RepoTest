<?php

namespace Tests\Feature\Client;

use App\Actions\Client\RespondToApplication;
use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Enums\TeamRole;
use App\Models\Application;
use App\Models\Project;
use App\Models\User;
use App\Notifications\Client\ApplicationReceived;
use App\Notifications\Client\ProjectPublished;
use App\Notifications\Client\ProjectStatusChanged;
use App\Notifications\Client\StudentAccepted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClientNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_team_is_notified_when_a_posting_is_submitted_for_review(): void
    {
        Notification::fake();

        $user = User::factory()->verifiedBusiness()->create();

        $this->actingAs($user)->post($this->url('projects.store', $user), $this->payload());

        Notification::assertSentTo($user, ProjectPublished::class);
    }

    public function test_saving_a_draft_does_not_announce_the_posting(): void
    {
        Notification::fake();

        $user = User::factory()->verifiedBusiness()->create();

        $this->actingAs($user)->post(
            $this->url('projects.store', $user),
            $this->payload(['status' => ProjectStatus::Draft->value]),
        );

        Notification::assertNothingSentTo($user);
    }

    public function test_publishing_a_draft_announces_it_once(): void
    {
        Notification::fake();

        $user = User::factory()->verifiedBusiness()->create();
        $project = Project::factory()->draft()->create([
            'team_id' => $user->current_team_id,
        ]);

        $this->actingAs($user)->patch(
            $this->url('projects.update', $user, ['project' => $project]),
            $this->payload(),
        );

        Notification::assertSentToTimes($user, ProjectPublished::class, 1);

        /* A second save of an already-published posting must stay quiet. */
        $this->actingAs($user)->patch(
            $this->url('projects.update', $user, ['project' => $project]),
            $this->payload(['title' => 'Renamed']),
        );

        Notification::assertSentToTimes($user, ProjectPublished::class, 1);
    }

    public function test_the_client_team_is_notified_when_a_student_applies(): void
    {
        Notification::fake();

        $owner = User::factory()->verifiedBusiness()->create();
        $project = Project::factory()->create(['team_id' => $owner->current_team_id]);

        Application::factory()->create(['project_id' => $project->id]);

        Notification::assertSentTo($owner, ApplicationReceived::class);
    }

    public function test_an_invited_student_does_not_trigger_an_application_received_notice(): void
    {
        Notification::fake();

        $owner = User::factory()->verifiedBusiness()->create();
        $project = Project::factory()->create(['team_id' => $owner->current_team_id]);

        Application::factory()->invited()->create(['project_id' => $project->id]);

        Notification::assertNotSentTo($owner, ApplicationReceived::class);
    }

    public function test_a_student_is_notified_when_accepted(): void
    {
        Notification::fake();

        $owner = User::factory()->verifiedBusiness()->create();
        $student = User::factory()->student()->create();
        $project = Project::factory()->create([
            'team_id' => $owner->current_team_id,
            'team_size' => 2,
        ]);

        $application = Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
        ]);

        app(RespondToApplication::class)
            ->handle($application, ApplicationStatus::Accepted, $owner);

        Notification::assertSentTo($student, StudentAccepted::class);
    }

    public function test_the_team_is_notified_when_the_project_becomes_fully_staffed(): void
    {
        Notification::fake();

        $owner = User::factory()->verifiedBusiness()->create();
        $project = Project::factory()->create([
            'team_id' => $owner->current_team_id,
            'team_size' => 1,
        ]);

        $application = Application::factory()->create(['project_id' => $project->id]);

        app(RespondToApplication::class)
            ->handle($application, ApplicationStatus::Accepted, $owner);

        $this->assertSame(ProjectStatus::InProgress, $project->refresh()->status);

        Notification::assertSentTo(
            $owner,
            ProjectStatusChanged::class,
            fn (ProjectStatusChanged $notification) => $notification->previousStatus === ProjectStatus::Open,
        );
    }

    public function test_an_understaffed_project_does_not_announce_a_status_change(): void
    {
        Notification::fake();

        $owner = User::factory()->verifiedBusiness()->create();
        $project = Project::factory()->create([
            'team_id' => $owner->current_team_id,
            'team_size' => 3,
        ]);

        $application = Application::factory()->create(['project_id' => $project->id]);

        app(RespondToApplication::class)
            ->handle($application, ApplicationStatus::Accepted, $owner);

        $this->assertSame(ProjectStatus::Open, $project->refresh()->status);

        Notification::assertNotSentTo($owner, ProjectStatusChanged::class);
    }

    public function test_archiving_a_project_notifies_the_team(): void
    {
        Notification::fake();

        $owner = User::factory()->verifiedBusiness()->create();
        $project = Project::factory()->create(['team_id' => $owner->current_team_id]);

        $this->actingAs($owner)
            ->patch($this->url('projects.archive', $owner, ['project' => $project]));

        Notification::assertSentTo($owner, ProjectStatusChanged::class);
    }

    public function test_every_team_member_receives_the_notification(): void
    {
        Notification::fake();

        $owner = User::factory()->verifiedBusiness()->create();
        $colleague = User::factory()->verifiedBusiness()->create();

        $owner->currentTeam->members()->attach($colleague, [
            'role' => TeamRole::Admin->value,
        ]);

        $project = Project::factory()->create(['team_id' => $owner->current_team_id]);

        Application::factory()->create(['project_id' => $project->id]);

        Notification::assertSentTo([$owner, $colleague], ApplicationReceived::class);
    }

    public function test_the_stored_payload_identifies_the_project_and_student(): void
    {
        $owner = User::factory()->verifiedBusiness()->create();
        $student = User::factory()->student()->create(['name' => 'Jeremie Caasi']);
        $project = Project::factory()->create([
            'team_id' => $owner->current_team_id,
            'title' => 'Inventory System',
        ]);

        $application = Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
        ]);

        $payload = (new ApplicationReceived($application))->toArray($owner);

        $this->assertSame('application.received', $payload['type']);
        $this->assertSame('Inventory System', $payload['project_title']);
        $this->assertSame('Jeremie Caasi', $payload['student_name']);
    }

    /**
     * Build a URL with the acting user's team pinned explicitly.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function url(string $name, User $user, array $parameters = []): string
    {
        return route($name, [
            'current_team' => $user->currentTeam,
            ...$parameters,
        ]);
    }

    /**
     * Build a valid posting payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Inventory System',
            'description' => 'Replace the spreadsheet used across three branches.',
            'category' => 'Management / inventory system',
            'skills' => ['Laravel'],
            'team_size' => 3,
            'experience_level' => 'any',
            'budget_type' => 'fixed',
            'budget_amount' => 28000,
            'milestones' => [],
            'visibility' => 'all_students',
            'status' => ProjectStatus::PendingReview->value,
        ], $overrides);
    }
}
