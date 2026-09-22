<?php

namespace Tests\Feature\Agreements;

use App\Actions\Agreements\SummariseProgress;
use App\Enums\MilestoneStatus;
use App\Enums\TaskStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AgreementMilestone;
use App\Models\AgreementTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Agreements\Concerns\StartsCollaboration;
use Tests\TestCase;

/**
 * The progress figure: tasks the client verified over every task.
 *
 * Computed on the server and never typed in. These pin the edge cases — no
 * tasks at all, work handed over but not yet verified, a phase completing and
 * reopening — and that the page and both dashboards report the same numbers.
 */
class TaskProgressTest extends TestCase
{
    use RefreshDatabase, StartsCollaboration;

    public function test_an_agreement_with_no_tasks_is_at_zero_not_an_error(): void
    {
        ['agreement' => $agreement] = $this->collaboration();

        $summary = app(SummariseProgress::class)->handle($agreement);

        $this->assertSame(0, $summary['progress']);
        $this->assertSame(0, $summary['taskCount']);
        $this->assertSame('Design', $summary['currentPhase']['title']);
        $this->assertSame('Build', $summary['nextMilestone']['title']);
        $this->assertSame([0, 0, 0], array_column($summary['phases'], 'progress'));
    }

    public function test_only_verified_tasks_count(): void
    {
        ['agreement' => $agreement] = $this->collaboration();
        [$design, $build] = $agreement->milestones;

        // Design: 2 verified, 1 submitted. Build: 1 open. Four tasks, two verified.
        $this->tasks($design, [TaskStatus::Verified, TaskStatus::Verified, TaskStatus::Submitted]);
        $this->tasks($build, [TaskStatus::Open]);

        $summary = app(SummariseProgress::class)->handle($agreement->fresh());

        $this->assertSame(50, $summary['progress']);
        $this->assertSame(2, $summary['verifiedCount']);
        $this->assertSame(1, $summary['submittedCount']);
        $this->assertSame(4, $summary['taskCount']);
        $this->assertSame(67, $summary['phases'][0]['progress']);
        $this->assertSame(0, $summary['phases'][1]['progress']);
        $this->assertSame(50, $agreement->fresh()->progress());
    }

    public function test_verifying_the_last_task_completes_the_phase_and_moves_the_current_phase_on(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration();
        $design = $agreement->milestones->first();
        [$done, $last] = $this->tasks($design, [TaskStatus::Verified, TaskStatus::Submitted]);

        $this->actingAs($client)
            ->post(route('agreements.tasks.verify', $this->asParty($client, $agreement, ['task' => $last])))
            ->assertSessionHasNoErrors();

        $design->refresh();
        $this->assertSame(MilestoneStatus::Approved, $design->status);
        $this->assertSame($client->id, $design->approved_by);

        $summary = app(SummariseProgress::class)->handle($agreement->fresh());
        $this->assertSame(SummariseProgress::PHASE_DONE, $summary['phases'][0]['state']);
        $this->assertSame(SummariseProgress::PHASE_IN_PROGRESS, $summary['phases'][1]['state']);
        $this->assertSame('Build', $summary['currentPhase']['title']);
        $this->assertSame('Turnover', $summary['nextMilestone']['title']);
    }

    public function test_adding_a_task_to_a_completed_phase_reopens_it(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $design = $agreement->milestones->first();
        $this->tasks($design, [TaskStatus::Verified]);
        $design->update(['status' => MilestoneStatus::Approved, 'approved_at' => now()]);

        $this->actingAs($student)
            ->post(route('agreements.tasks.store', $this->asParty($student, $agreement, ['milestone' => $design])), ['title' => 'Late addition', 'due_on' => '2026-02-20'])
            ->assertSessionHasNoErrors();

        $design->refresh();
        $this->assertSame(MilestoneStatus::InProgress, $design->status);
        $this->assertNull($design->approved_at);
        $this->assertSame(50, $agreement->fresh()->progress());
    }

    public function test_the_phase_status_follows_its_checklist(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $design = $agreement->milestones->first();
        [$task] = $this->tasks($design, [TaskStatus::Open]);

        $this->actingAs($student)
            ->post(route('agreements.tasks.submit', $this->asParty($student, $agreement, ['task' => $task])), ['proof_note' => 'Done'])
            ->assertSessionHasNoErrors();

        // Every outstanding task handed over reads as "in review".
        $this->assertSame(MilestoneStatus::Submitted, $design->refresh()->status);

        $this->actingAs($student)
            ->delete(route('agreements.tasks.withdraw', $this->asParty($student, $agreement, ['task' => $task])));

        $this->assertSame(MilestoneStatus::Pending, $design->refresh()->status);
    }

    public function test_the_page_and_both_dashboards_report_the_same_figures(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        [$design, $build] = $agreement->milestones;
        $this->tasks($design, [TaskStatus::Verified, TaskStatus::Verified]);
        $this->tasks($build, [TaskStatus::Verified, TaskStatus::Submitted, TaskStatus::Open]);

        // 3 of 5 verified; Design done, Build current at 33%.
        $this->actingAs($student)
            ->get(route('project-management', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('agreement.summary.progress', 60)
                ->where('agreement.summary.verifiedCount', 3)
                ->where('agreement.summary.taskCount', 5)
                ->where('agreement.summary.currentPhase.title', 'Build')
                ->where('agreement.phases.1.progress', 33));

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('project.progress', 60)
                ->where('project.verifiedCount', 3)
                ->where('project.taskCount', 5)
                ->where('project.currentPhase', 'Build')
                ->where('project.nextMilestone.title', 'Turnover')
                ->where('project.phases.0.isDone', true)
                ->where('project.phases.1.progress', 33));

        $this->actingAs($client)
            ->get(route('client.dashboard', ['current_team' => $client->currentTeam]), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'client/dashboard',
                'X-Inertia-Partial-Data' => 'currentProject',
            ])
            ->assertOk()
            ->assertJsonPath('props.currentProject.progress', 60)
            ->assertJsonPath('props.currentProject.verifiedCount', 3)
            ->assertJsonPath('props.currentProject.taskCount', 5)
            ->assertJsonPath('props.currentProject.currentPhase', 'Build')
            ->assertJsonPath('props.currentProject.milestones.1.progress', 33);
    }

    public function test_a_planned_date_moves_the_next_milestone_due_date(): void
    {
        ['agreement' => $agreement] = $this->collaboration();
        $agreement->milestones[1]->update(['planned_ends_on' => '2026-04-03']);

        $summary = app(SummariseProgress::class)->handle($agreement->fresh());

        $this->assertSame('3 Apr 2026', $summary['nextMilestone']['dueOn']);
    }

    /**
     * Give a phase tasks in the given statuses, in order.
     *
     * @param  list<TaskStatus>  $statuses
     * @return list<AgreementTask>
     */
    private function tasks(AgreementMilestone $milestone, array $statuses): array
    {
        return array_map(
            fn (TaskStatus $status, int $index): AgreementTask => AgreementTask::factory()->create([
                'agreement_milestone_id' => $milestone->id,
                'position' => $index + 1,
                'status' => $status,
            ]),
            $statuses,
            array_keys($statuses),
        );
    }
}
