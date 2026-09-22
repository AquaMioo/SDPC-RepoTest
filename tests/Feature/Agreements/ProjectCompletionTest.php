<?php

namespace Tests\Feature\Agreements;

use App\Actions\Notifications\PresentNotification;
use App\Enums\AgreementStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Models\Agreement;
use App\Models\AgreementTask;
use App\Models\Project;
use App\Models\User;
use App\Notifications\Agreements\ProjectCompleted;
use App\Notifications\Client\ProjectStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Agreements\Concerns\StartsCollaboration;
use Tests\TestCase;

/**
 * The client's Complete button: the end of a collaboration.
 *
 * Completing turns the project and every agreement on it Completed, which is
 * what frees the students (signer and teammates) to apply again and the
 * business to post again. Only the client may do it, and only once.
 */
class ProjectCompletionTest extends TestCase
{
    use RefreshDatabase, StartsCollaboration;

    public function test_the_client_completes_the_project_and_its_agreement(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration();

        $this->actingAs($client)
            ->post($this->completeUrl($client, $agreement))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('project-management', ['current_team' => $client->currentTeam, 'agreement' => $agreement->id]));

        $project = $agreement->project->fresh();
        $agreement->refresh();

        $this->assertSame(ProjectStatus::Completed, $project->status);
        $this->assertNotNull($project->completed_at);
        $this->assertFalse($project->applications_open);
        $this->assertSame(AgreementStatus::Completed, $agreement->status);
        $this->assertNotNull($agreement->completed_at);
    }

    public function test_completing_frees_the_student_and_the_business(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();

        $this->assertTrue($student->isLockedToProject());
        $this->assertFalse(Gate::forUser($client)->allows('create', Project::class));

        $this->actingAs($client)->post($this->completeUrl($client, $agreement));

        $this->assertFalse($student->fresh()->isLockedToProject());
        $this->assertTrue(Gate::forUser($client->fresh())->allows('create', Project::class));
    }

    public function test_every_agreement_on_the_posting_ends_together(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration();
        $second = Agreement::factory()->create([
            'project_id' => $agreement->project_id,
            'team_id' => $agreement->team_id,
            'status' => AgreementStatus::Active,
        ]);

        $this->actingAs($client)->post($this->completeUrl($client, $agreement));

        $this->assertSame(AgreementStatus::Completed, $second->fresh()->status);
    }

    public function test_the_student_side_and_the_business_are_told(): void
    {
        Notification::fake();

        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $teammate = $this->teammateOf($student);

        $this->actingAs($client)->post($this->completeUrl($client, $agreement));

        Notification::assertSentTo([$student, $teammate], ProjectCompleted::class);
        Notification::assertSentTo($client, ProjectStatusChanged::class);
        Notification::assertNotSentTo($client, ProjectCompleted::class);
    }

    public function test_the_completed_notification_reads_in_the_bell(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();

        $this->actingAs($client)->post($this->completeUrl($client, $agreement));

        $data = $student->fresh()->notifications()->first()?->data;

        $this->assertSame('project.completed', $data['type'] ?? null);
        $this->assertSame($agreement->id, $data['agreement_id']);

        $row = app(PresentNotification::class)->handle($student->fresh()->notifications()->firstOrFail(), $student->currentTeam);

        $this->assertSame($agreement->project->title.' is complete', $row['title']);
        $this->assertStringContainsString('agreement='.$agreement->id, (string) $row['url']);
    }

    public function test_only_the_client_may_complete(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $teammate = $this->teammateOf($student);
        $otherClient = User::factory()->client()->verifiedBusiness()->create();

        $this->actingAs($student)->post($this->completeUrl($student, $agreement))->assertForbidden();
        $this->actingAs($teammate)->post(route('agreements.completion.store', [
            'current_team' => $student->currentTeam,
            'agreement' => $agreement,
        ]))->assertForbidden();
        $this->actingAs($otherClient)->post($this->completeUrl($otherClient, $agreement))->assertForbidden();

        $this->assertSame(AgreementStatus::Active, $agreement->fresh()->status);
        $this->assertSame(ProjectStatus::InProgress, $agreement->project->fresh()->status);
    }

    public function test_an_agreement_nobody_has_signed_cannot_be_completed(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration(AgreementStatus::AwaitingSignatures);

        $this->actingAs($client)->post($this->completeUrl($client, $agreement))->assertForbidden();

        $this->assertSame(AgreementStatus::AwaitingSignatures, $agreement->fresh()->status);
    }

    public function test_completing_twice_changes_nothing_the_second_time(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration();

        $this->actingAs($client)->post($this->completeUrl($client, $agreement))->assertSessionHasNoErrors();
        $completedAt = $agreement->fresh()->completed_at;

        $this->actingAs($client)->post($this->completeUrl($client, $agreement))->assertForbidden();

        $this->assertEquals($completedAt, $agreement->fresh()->completed_at);
    }

    public function test_a_project_that_is_not_in_progress_is_refused(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration();
        $agreement->project->update(['status' => ProjectStatus::Closed]);

        $this->actingAs($client)
            ->post($this->completeUrl($client, $agreement))
            ->assertSessionHasErrors('project');

        $this->assertSame(AgreementStatus::Active, $agreement->fresh()->status);
    }

    public function test_the_client_may_complete_with_tasks_still_unverified(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration();
        AgreementTask::factory()->create([
            'agreement_milestone_id' => $agreement->milestones->first()->id,
            'status' => TaskStatus::Submitted,
        ]);

        $this->actingAs($client)->post($this->completeUrl($client, $agreement))->assertSessionHasNoErrors();

        $this->assertSame(AgreementStatus::Completed, $agreement->fresh()->status);
    }

    public function test_a_completed_build_stays_readable_but_nothing_can_be_written(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = AgreementTask::factory()->create(['agreement_milestone_id' => $agreement->milestones->first()->id]);

        $this->actingAs($client)->post($this->completeUrl($client, $agreement));

        $this->actingAs($student)
            ->get(route('project-management', ['current_team' => $student->currentTeam, 'agreement' => $agreement->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('agreement.id', $agreement->id)
                ->where('agreement.isCompleted', true)
                ->where('can.manage', false)
                ->where('can.complete', false)
                ->where('isLocked', false)
                ->where('completedAgreements.0.id', $agreement->id));

        $this->actingAs($student)
            ->post(route('agreements.tasks.submit', ['current_team' => $student->currentTeam, 'agreement' => $agreement, 'task' => $task]), ['proof_note' => 'Late'])
            ->assertForbidden();
    }

    public function test_the_screen_offers_complete_to_the_client_only(): void
    {
        ['client' => $client, 'student' => $student] = $this->collaboration();

        $this->actingAs($client)
            ->get(route('project-management', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn ($page) => $page->where('can.complete', true));

        $this->actingAs($student)
            ->get(route('project-management', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn ($page) => $page->where('can.complete', false)->where('isLocked', true));
    }

    private function completeUrl(User $user, Agreement $agreement): string
    {
        return route('agreements.completion.store', $this->asParty($user, $agreement));
    }

    private function teammateOf(User $student): User
    {
        $teammate = User::factory()->student()->approved()->create();
        $student->currentTeam->members()->attach($teammate, ['role' => TeamRole::Member->value]);
        $teammate->switchTeam($student->currentTeam);

        return $teammate->refresh();
    }
}
