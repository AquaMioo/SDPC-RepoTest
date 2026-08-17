<?php

namespace Tests\Feature\Student;

use App\Enums\AgreementStatus;
use App\Enums\ApplicationStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\Application;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Project Progress Tracking, from the student's side.
 *
 * Every figure is a milestone somebody moved by hand. Nothing here is inferred
 * from a date having passed.
 */
class ProjectProcessTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_screen_is_empty_without_a_signed_agreement(): void
    {
        $student = User::factory()->student()->approved()->create();

        $this->actingAs($student)
            ->get(route('student.process', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('student/process')
                ->where('agreement', null));
    }

    public function test_a_draft_is_not_progress(): void
    {
        [$student, $agreement] = $this->build();

        // Terms nobody has signed are a proposal. Reporting progress against
        // them would put a number on work that was never agreed to.
        $agreement->update(['status' => AgreementStatus::Draft]);

        $this->actingAs($student)
            ->get(route('student.process', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('agreement', null));
    }

    public function test_the_milestones_and_their_average_are_shown(): void
    {
        [$student, $agreement] = $this->build([
            MilestoneStatus::Approved,
            MilestoneStatus::Submitted,
            MilestoneStatus::Pending,
        ]);

        $this->actingAs($student)
            ->get(route('student.process', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('agreement.milestones', 3)
                // 100 + 80 + 0 over three.
                ->where('agreement.progress', 60)
                ->where('agreement.reference', $agreement->reference)
                ->where('agreement.milestones.0.statusLabel', 'Approved'));
    }

    public function test_a_student_is_only_offered_their_own_half_of_the_moves(): void
    {
        [$student] = $this->build();

        $this->actingAs($student)
            ->get(route('student.process', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('assignableStatuses', [
                    ['value' => 'in_progress', 'label' => 'In progress'],
                    ['value' => 'submitted', 'label' => 'In review'],
                ]));
    }

    public function test_a_student_hands_a_milestone_over(): void
    {
        [$student, $agreement] = $this->build();

        $milestone = $agreement->milestones->first();

        $this->actingAs($student)
            ->patch(route('agreements.milestones.update', [
                'current_team' => $student->currentTeam,
                'agreement' => $agreement,
                'milestone' => $milestone,
            ]), ['status' => MilestoneStatus::Submitted->value])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(MilestoneStatus::Submitted, $milestone->refresh()->status);
        $this->assertNotNull($milestone->submitted_at);
    }

    public function test_a_milestone_belonging_to_a_stranger_is_refused(): void
    {
        [$student, $agreement] = $this->build();
        [, $other] = $this->build();

        $this->actingAs($student)
            ->patch(route('agreements.milestones.update', [
                'current_team' => $student->currentTeam,
                'agreement' => $agreement,
                'milestone' => $other->milestones->first(),
            ]), ['status' => MilestoneStatus::Submitted->value])
            ->assertForbidden();
    }

    public function test_a_milestone_from_the_students_other_contract_is_not_found(): void
    {
        [$student, $first] = $this->build();

        // A second contract the same student is party to — a superseded round
        // of terms, or a finished build. The request authorises them either
        // way, so the URL's agreement is what has to hold the line.
        $second = Agreement::factory()->active()->create([
            'project_id' => $first->project_id,
            'application_id' => $first->application_id,
            'team_id' => $first->team_id,
            'student_id' => $student->id,
            'version' => 2,
        ]);

        $stray = AgreementMilestone::factory()->create([
            'agreement_id' => $second->id,
            'position' => 1,
        ]);

        $this->actingAs($student)
            ->patch(route('agreements.milestones.update', [
                'current_team' => $student->currentTeam,
                'agreement' => $first,
                'milestone' => $stray,
            ]), ['status' => MilestoneStatus::Submitted->value])
            ->assertNotFound();

        $this->assertSame(MilestoneStatus::Pending, $stray->refresh()->status);
    }

    public function test_a_student_cannot_approve_their_own_work(): void
    {
        [$student, $agreement] = $this->build();

        $milestone = $agreement->milestones->first();

        // Approval is the client's, and the request refuses it rather than
        // leaving a student able to sign off their own delivery.
        $this->actingAs($student)
            ->patch(route('agreements.milestones.update', [
                'current_team' => $student->currentTeam,
                'agreement' => $agreement,
                'milestone' => $milestone,
            ]), ['status' => MilestoneStatus::Approved->value])
            ->assertSessionHasErrors('status');

        $this->assertSame(MilestoneStatus::Pending, $milestone->refresh()->status);
    }

    /**
     * An active agreement with three milestones on it.
     *
     * @param  list<MilestoneStatus>  $statuses
     * @return array{0: User, 1: Agreement}
     */
    private function build(array $statuses = []): array
    {
        $owner = User::factory()->verifiedBusiness()->create();
        $student = User::factory()->student()->approved()->create();

        $project = Project::factory()->create([
            'team_id' => $owner->current_team_id,
            'status' => ProjectStatus::InProgress,
        ]);

        $application = Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Accepted,
        ]);

        $agreement = Agreement::factory()->active()->create([
            'project_id' => $project->id,
            'application_id' => $application->id,
            'team_id' => $owner->current_team_id,
            'student_id' => $student->id,
        ]);

        foreach ($statuses ?: [MilestoneStatus::Pending] as $index => $status) {
            AgreementMilestone::factory()->create([
                'agreement_id' => $agreement->id,
                'position' => $index + 1,
                'status' => $status,
            ]);
        }

        return [$student, $agreement->refresh()->load('milestones')];
    }
}
