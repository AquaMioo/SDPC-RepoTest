<?php

namespace Tests\Feature\Agreements;

use App\Enums\AgreementStatus;
use App\Enums\ApplicationStatus;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Agreements\Concerns\StartsCollaboration;
use Tests\TestCase;

/**
 * The Project Management screen both sides share.
 *
 * It replaces the student's Workflow tab and the client's Project Process tab,
 * and opens only once the two sides are collaborating — a signed, active
 * agreement. Before that the page is still reachable but locked.
 */
class ProjectManagementTest extends TestCase
{
    use RefreshDatabase, StartsCollaboration;

    public function test_a_student_without_a_signed_agreement_sees_the_locked_page_and_their_applications(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration(AgreementStatus::AwaitingSignatures);

        $this->actingAs($student)
            ->get(route('project-management', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('project-management/index')
                ->where('side', 'student')
                ->where('agreement', null)
                ->where('can.manage', false)
                ->where('pendingAgreementId', $agreement->id)
                ->has('applications', 1)
                ->where('applications.0.status', ApplicationStatus::Accepted->value));
    }

    public function test_a_client_without_a_signed_agreement_sees_the_locked_page(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get(route('project-management', ['current_team' => $client->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('side', 'client')
                ->where('agreement', null)
                ->where('pendingAgreementId', null)
                ->has('applications', 0));
    }

    public function test_the_student_is_the_updater(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();

        $this->actingAs($student)
            ->get(route('project-management', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('agreement.id', $agreement->id)
                ->where('can.manage', true)
                ->where('can.verify', false)
                ->has('agreement.phases', 3)
                ->where('agreement.phases.0.title', 'Design')
                ->where('agreement.phases.0.startsOn', '2026-02-02')
                ->where('agreement.phases.0.state', 'in_progress'));
    }

    public function test_the_client_is_the_verifier(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();

        $this->actingAs($client)
            ->get(route('project-management', ['current_team' => $client->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('side', 'client')
                ->where('agreement.id', $agreement->id)
                ->where('agreement.counterpart', $student->name)
                ->where('can.manage', false)
                ->where('can.verify', true));
    }

    public function test_a_client_running_two_builds_can_switch_between_them_but_not_into_someone_elses(): void
    {
        ['client' => $client, 'agreement' => $first] = $this->collaboration();

        $secondStudent = User::factory()->student()->approved()->create();
        $second = Agreement::factory()->active()->create([
            'project_id' => $first->project_id,
            'application_id' => Application::factory()->create([
                'project_id' => $first->project_id,
                'user_id' => $secondStudent->id,
                'status' => ApplicationStatus::Accepted,
            ])->id,
            'team_id' => $first->team_id,
            'student_id' => $secondStudent->id,
            'activated_at' => now()->addMinute(),
        ]);
        AgreementMilestone::factory()->create(['agreement_id' => $second->id]);

        ['agreement' => $foreign] = $this->collaboration();

        $url = fn (array $query) => route('project-management', ['current_team' => $client->currentTeam, ...$query]);

        $this->actingAs($client)->get($url([]))
            ->assertInertia(fn (Assert $page) => $page->has('agreements', 2)->where('agreement.id', $second->id));

        $this->actingAs($client)->get($url(['agreement' => $first->id]))
            ->assertInertia(fn (Assert $page) => $page->where('agreement.id', $first->id));

        // Another business's agreement id is not in the set, so the newest of
        // this client's own is shown instead.
        $this->actingAs($client)->get($url(['agreement' => $foreign->id]))
            ->assertInertia(fn (Assert $page) => $page->where('agreement.id', $second->id));
    }

    public function test_the_old_workflow_and_process_addresses_lead_here(): void
    {
        $student = User::factory()->student()->approved()->create();
        $target = route('project-management', ['current_team' => $student->currentTeam]);

        $this->actingAs($student)
            ->get(route('student.workflow', ['current_team' => $student->currentTeam]))
            ->assertRedirect($target);

        $this->actingAs($student)
            ->get(route('student.process', ['current_team' => $student->currentTeam]))
            ->assertRedirect($target);
    }

    public function test_a_proof_file_is_linked_through_the_checked_route(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $agreement->milestones->first()->tasks()->create([
            'position' => 1,
            'title' => 'UI wireframes',
            'status' => 'submitted',
            'proof_path' => 'task-proofs/'.$agreement->id.'/shot.png',
            'proof_name' => 'shot.png',
        ]);

        $this->actingAs($student)
            ->get(route('project-management', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('agreement.phases.0.tasks.0.proofName', 'shot.png')
                ->where('agreement.phases.0.tasks.0.proofHref', route('agreements.tasks.proof', [
                    'current_team' => $student->currentTeam,
                    'agreement' => $agreement,
                    'task' => $task,
                ])));
    }
}
