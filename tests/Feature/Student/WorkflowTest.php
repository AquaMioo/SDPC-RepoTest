<?php

namespace Tests\Feature\Student;

use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Models\Application;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * "Workflow" became "Project Management".
 *
 * The old address redirects to the shared screen, and the applications list
 * this page used to carry is a section of it now. These keep pinning what the
 * list shows; the monitoring half is covered in tests/Feature/Agreements.
 */
class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_can_open_their_workflow(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->get(route('student.workflow', ['current_team' => $student->currentTeam]))
            ->assertRedirect(route('project-management', ['current_team' => $student->currentTeam]));

        $this->screen($student)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('project-management/index'));
    }

    public function test_a_client_is_kept_out(): void
    {
        $client = User::factory()->client()->approved()->create();

        $this->actingAs($client)
            ->get(route('student.workflow', ['current_team' => $client->currentTeam]))
            ->assertForbidden();
    }

    public function test_a_new_student_sees_both_halves_empty(): void
    {
        $student = $this->student();

        $this->screen($student)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement', null)
                ->has('applications', 0));
    }

    public function test_an_accepted_application_waits_for_a_signed_agreement(): void
    {
        $student = $this->student();
        $project = Project::factory()->create([
            'title' => 'Inventory System',
            'status' => ProjectStatus::InProgress,
        ]);

        $this->apply($student, $project, ApplicationStatus::Accepted);

        // Accepted is not collaborating yet: the build is tracked once both
        // sides sign, so the monitoring half stays locked.
        $this->screen($student)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement', null)
                ->has('applications', 1)
                ->where('applications.0.projectTitle', 'Inventory System'));
    }

    public function test_a_pending_application_can_still_be_withdrawn(): void
    {
        $student = $this->student();
        $project = Project::factory()->create(['status' => ProjectStatus::Open]);

        $this->apply($student, $project, ApplicationStatus::Pending);

        $this->screen($student)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('applications', 1)
                ->where('applications.0.canWithdraw', true));
    }

    public function test_a_decided_application_can_no_longer_be_withdrawn_from_the_screen(): void
    {
        $student = $this->student();

        $this->apply($student, Project::factory()->create(), ApplicationStatus::Rejected);

        $this->screen($student)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('applications.0.canWithdraw', false));
    }

    public function test_a_finished_project_keeps_its_application(): void
    {
        $student = $this->student();
        $project = Project::factory()->create(['status' => ProjectStatus::Completed]);

        $this->apply($student, $project, ApplicationStatus::Accepted);

        $this->screen($student)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement', null)
                ->has('applications', 1));
    }

    public function test_one_student_never_sees_anothers_applications(): void
    {
        $student = $this->student();
        $stranger = $this->student();

        $this->apply($stranger, Project::factory()->create(), ApplicationStatus::Accepted);

        $this->screen($student)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement', null)
                ->has('applications', 0));
    }

    /**
     * An approved student on their personal team.
     */
    private function student(): User
    {
        return User::factory()->student()->approved()->create();
    }

    /**
     * Open Project Management as the student, on their own team.
     */
    private function screen(User $student): TestResponse
    {
        return $this->actingAs($student)
            ->get(route('project-management', ['current_team' => $student->currentTeam]));
    }

    /**
     * Put an application of the given status on the books.
     */
    private function apply(User $student, Project $project, ApplicationStatus $status): void
    {
        Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => $status,
        ]);
    }
}
