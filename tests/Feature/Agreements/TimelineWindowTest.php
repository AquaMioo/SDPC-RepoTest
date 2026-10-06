<?php

namespace Tests\Feature\Agreements;

use App\Enums\DeadlineRequestStatus;
use App\Models\AgreementTask;
use App\Models\DeadlineChangeRequest;
use App\Support\TimelineWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Agreements\Concerns\StartsCollaboration;
use Tests\TestCase;

/**
 * Every date set on Project Management stays between today and one year
 * from today (Singapore Time), and Turnover keeps at least a month.
 *
 * The collaboration fixture runs Design 2 Feb – 22 Feb, Build 23 Feb – 29 Mar
 * and Turnover 30 Mar – 12 Apr 2026. Every test runs on 10 Feb 2026, in the
 * middle of Design.
 */
class TimelineWindowTest extends TestCase
{
    use RefreshDatabase, StartsCollaboration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-02-10 09:00:00');
    }

    public function test_the_window_runs_from_today_to_a_year_from_today(): void
    {
        $this->assertSame('2026-02-10', TimelineWindow::earliest()->toDateString());
        $this->assertSame('2027-02-10', TimelineWindow::latest()->toDateString());
        $this->assertTrue(TimelineWindow::contains(Carbon::parse('2026-02-10')));
        $this->assertTrue(TimelineWindow::contains(Carbon::parse('2027-02-10')));
        $this->assertFalse(TimelineWindow::contains(Carbon::parse('2026-02-09')));
        $this->assertFalse(TimelineWindow::contains(Carbon::parse('2027-02-11')));

        /* A month without spilling over: 31 Jan runs to 28 Feb. */
        $this->assertSame('2026-02-28', TimelineWindow::earliestTurnoverEnd(Carbon::parse('2026-01-31'))->toDateString());
        $this->assertTrue(TimelineWindow::isLongEnoughForTurnover(Carbon::parse('2026-03-30'), Carbon::parse('2026-04-30')));
        $this->assertFalse(TimelineWindow::isLongEnoughForTurnover(Carbon::parse('2026-03-30'), Carbon::parse('2026-04-29')));
    }

    /**
     * Design started last week. Moving its end is fine; moving its start to
     * another past day is not.
     */
    public function test_a_phase_date_that_moves_cannot_be_in_the_past(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $design = $agreement->milestones->first();
        $url = route('agreements.milestones.schedule', $this->asParty($student, $agreement, ['milestone' => $design]));

        $this->actingAs($student)
            ->patch($url, ['starts_on' => '2026-02-02', 'ends_on' => '2026-02-25'])
            ->assertSessionHasNoErrors();

        $this->actingAs($student)
            ->patch($url, ['starts_on' => '2026-02-05', 'ends_on' => '2026-02-25'])
            ->assertSessionHasErrors(['starts_on' => 'Dates cannot be in the past. Pick today or a later date.']);

        $this->actingAs($student)
            ->patch($url, ['starts_on' => '2026-02-02', 'ends_on' => '2026-02-09'])
            ->assertSessionHasErrors(['ends_on' => 'Dates cannot be in the past. Pick today or a later date.']);

        $this->assertSame('2026-02-25', $design->fresh()->scheduledEndsOn()->toDateString());
    }

    public function test_a_phase_date_cannot_be_more_than_a_year_out(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $build = $agreement->milestones[1];

        $this->actingAs($student)
            ->patch(route('agreements.milestones.schedule', $this->asParty($student, $agreement, ['milestone' => $build])), [
                'starts_on' => '2026-02-23',
                'ends_on' => '2027-02-11',
            ])
            ->assertSessionHasErrors(['ends_on' => 'Dates cannot be more than one year from today. Pick 10 Feb 2027 or earlier.']);
    }

    public function test_a_task_deadline_stays_inside_the_window(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        /* Only a Turnover task carries a deadline. */
        $design = $agreement->milestones->last();
        $url = route('agreements.tasks.store', $this->asParty($student, $agreement, ['milestone' => $design]));

        $this->actingAs($student)->post($url, ['title' => 'Yesterday', 'due_on' => '2026-02-09'])
            ->assertSessionHasErrors(['due_on' => 'Dates cannot be in the past. Pick today or a later date.']);

        $this->actingAs($student)->post($url, ['title' => 'Today', 'due_on' => '2026-02-10'])
            ->assertSessionHasNoErrors();

        /* Saving a task that keeps a deadline now passed is not setting one. */
        $overdue = AgreementTask::factory()->create(['agreement_milestone_id' => $design->id, 'due_on' => '2026-02-05']);

        $this->actingAs($student)
            ->patch(route('agreements.tasks.update', $this->asParty($student, $agreement, ['task' => $overdue])), [
                'title' => 'Renamed',
                'due_on' => '2026-02-05',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $overdue->fresh()->title);
    }

    public function test_a_date_asked_for_stays_inside_the_window(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = AgreementTask::factory()->create(['agreement_milestone_id' => $agreement->milestones->first()->id, 'due_on' => '2026-02-15']);
        $url = route('agreements.tasks.deadline-requests.store', $this->asParty($student, $agreement, ['task' => $task]));

        $this->actingAs($student)->post($url, ['proposed_on' => '2026-02-01'])
            ->assertSessionHasErrors(['proposed_on' => 'Dates cannot be in the past. Pick today or a later date.']);

        $this->actingAs($student)->post($url, ['proposed_on' => '2027-03-01'])
            ->assertSessionHasErrors(['proposed_on' => 'Dates cannot be more than one year from today. Pick 10 Feb 2027 or earlier.']);

        $this->assertSame(0, DeadlineChangeRequest::query()->count());
    }

    /**
     * An ask can wait. Once its date has passed the client cannot approve it
     * into the past; they decline and the student asks again.
     */
    public function test_an_ask_whose_date_has_passed_cannot_be_approved(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = AgreementTask::factory()->create(['agreement_milestone_id' => $agreement->milestones->first()->id, 'due_on' => '2026-02-15']);

        $this->actingAs($student)
            ->post(route('agreements.tasks.deadline-requests.store', $this->asParty($student, $agreement, ['task' => $task])), ['proposed_on' => '2026-02-12'])
            ->assertSessionHasNoErrors();

        $request = DeadlineChangeRequest::query()->firstOrFail();

        $this->travelTo('2026-02-13 09:00:00');

        $this->actingAs($client)
            ->post(route('agreements.deadline-requests.approve', $this->asParty($client, $agreement, ['deadlineRequest' => $request])))
            ->assertSessionHasErrors(['deadline' => 'The date asked for is no longer between today and one year from today. Decline this and ask the student for a new one.']);

        $this->assertSame(DeadlineRequestStatus::Pending, $request->fresh()->status);
        $this->assertSame('2026-02-15', $task->fresh()->due_on->toDateString());
    }

    /**
     * The fixture's Turnover (30 Mar – 12 Apr) predates the month rule; the
     * final deadline can only move to a day that gives it a full month.
     */
    public function test_the_final_deadline_cannot_leave_turnover_under_a_month(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $turnover = $agreement->milestones->last();
        $url = route('agreements.milestones.deadline-requests.store', $this->asParty($student, $agreement, ['milestone' => $turnover]));

        $this->actingAs($student)->post($url, ['proposed_on' => '2026-04-20'])
            ->assertSessionHasErrors(['proposed_on' => 'Turnover has to run at least one month. End it on or after 30 Apr 2026.']);

        $this->actingAs($student)->post($url, ['proposed_on' => '2026-04-30'])
            ->assertSessionHasNoErrors();
    }
}
