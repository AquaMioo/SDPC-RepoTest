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
        ]);

        $application = Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
        ]);

        app(RespondToApplication::class)
            ->handle($application, ApplicationStatus::Accepted, $owner);

        Notification::assertSentTo($student, StudentAccepted::class);
    }

    public function test_accepting_a_student_drafts_an_agreement_without_starting_the_project(): void
    {
        Notification::fake();

        $owner = User::factory()->verifiedBusiness()->create();
        $project = Project::factory()->create([
            'team_id' => $owner->current_team_id,
        ]);

        $application = Application::factory()->create(['project_id' => $project->id]);

        app(RespondToApplication::class)
            ->handle($application, ApplicationStatus::Accepted, $owner);

        // Acceptance is an offer of terms, not the start of work. The posting
        // only moves once both sides have signed — see SignAgreement.
        $this->assertSame(ProjectStatus::Open, $project->refresh()->status);
        $this->assertNotNull($application->refresh()->agreement);

        Notification::assertNotSentTo($owner, ProjectStatusChanged::class);
    }

    public function test_a_second_acceptance_reuses_nothing_and_drafts_its_own_agreement(): void
    {
        Notification::fake();

        $owner = User::factory()->verifiedBusiness()->create();
        $project = Project::factory()->create([
            'team_id' => $owner->current_team_id,
            'status' => ProjectStatus::Open,
        ]);

        $first = Application::factory()->create(['project_id' => $project->id]);
        $second = Application::factory()->create(['project_id' => $project->id]);

        app(RespondToApplication::class)->handle($first, ApplicationStatus::Accepted, $owner);
        app(RespondToApplication::class)->handle($second, ApplicationStatus::Accepted, $owner);

        // One contract per student, never a shared one, and neither of them
        // announces a project start that has not happened yet.
        $this->assertNotSame(
            $first->refresh()->agreement->id,
            $second->refresh()->agreement->id,
        );

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
            'status' => ProjectStatus::PendingReview->value,
        ], $overrides);
    }
}
