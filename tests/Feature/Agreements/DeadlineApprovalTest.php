<?php

namespace Tests\Feature\Agreements;

use App\Actions\Notifications\PresentNotification;
use App\Enums\DeadlineRequestStatus;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Models\Agreement;
use App\Models\AgreementTask;
use App\Models\DeadlineChangeRequest;
use App\Models\User;
use App\Notifications\Agreements\DeadlineChangeDecided;
use App\Notifications\Agreements\DeadlineChangeRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Agreements\Concerns\StartsCollaboration;
use Tests\TestCase;

/**
 * Deadlines are flexible, but watched.
 *
 * A task in Design or Build carries a deadline, set freely once. After that
 * it — and the final deadline, the end of Turnover — moves only when the
 * student side asks and the client approves. Turnover may not overlap the
 * other phases, which may overlap each other.
 *
 * The collaboration fixture runs Design 2 Feb – 22 Feb, Build 23 Feb – 29 Mar
 * and Turnover 30 Mar – 12 Apr 2026, so the final deadline is 12 Apr.
 */
class DeadlineApprovalTest extends TestCase
{
    use RefreshDatabase, StartsCollaboration;

    public function test_a_design_or_build_task_needs_a_deadline_but_a_turnover_one_does_not(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        [$design, , $turnover] = $agreement->milestones;

        $this->actingAs($student)
            ->post(route('agreements.tasks.store', $this->asParty($student, $agreement, ['milestone' => $design])), ['title' => 'Wireframes'])
            ->assertSessionHasErrors(['due_on' => 'Give the task a deadline.']);

        $this->actingAs($student)
            ->post(route('agreements.tasks.store', $this->asParty($student, $agreement, ['milestone' => $turnover])), ['title' => 'Hand over'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $design->tasks()->count());
        $this->assertNull($turnover->tasks()->first()->due_on);
    }

    public function test_a_task_deadline_falls_on_or_before_the_final_deadline(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $url = route('agreements.tasks.store', $this->asParty($student, $agreement, ['milestone' => $agreement->milestones[1]]));

        $this->actingAs($student)->post($url, ['title' => 'Too late', 'due_on' => '2026-04-13'])
            ->assertSessionHasErrors(['due_on' => 'A task has to be due on or before the final deadline, 12 Apr 2026.']);

        $this->actingAs($student)->post($url, ['title' => 'Just in time', 'due_on' => '2026-04-12'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-04-12', $agreement->milestones[1]->tasks()->first()->due_on->toDateString());
    }

    public function test_editing_sets_a_first_deadline_but_never_moves_one(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $legacy = $this->task($agreement, dueOn: null);
        $dated = $this->task($agreement, dueOn: '2026-02-15');

        $this->actingAs($student)
            ->patch(route('agreements.tasks.update', $this->asParty($student, $agreement, ['task' => $legacy])), ['title' => $legacy->title, 'due_on' => '2026-02-18'])
            ->assertSessionHasNoErrors();

        $this->actingAs($student)
            ->patch(route('agreements.tasks.update', $this->asParty($student, $agreement, ['task' => $dated])), ['title' => $dated->title, 'due_on' => '2026-03-01'])
            ->assertSessionHasErrors('due_on');

        $this->actingAs($student)
            ->patch(route('agreements.tasks.update', $this->asParty($student, $agreement, ['task' => $dated])), ['title' => 'Renamed', 'due_on' => '2026-02-15'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-02-18', $legacy->fresh()->due_on->toDateString());
        $this->assertSame('2026-02-15', $dated->fresh()->due_on->toDateString());
        $this->assertSame('Renamed', $dated->fresh()->title);
    }

    public function test_the_student_asks_and_nothing_moves_until_the_client_answers(): void
    {
        Notification::fake();

        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement, dueOn: '2026-02-15');

        $this->actingAs($student)
            ->post($this->askUrl($student, $agreement, $task), ['proposed_on' => '2026-02-25', 'reason' => 'The API docs arrived late.'])
            ->assertSessionHasNoErrors();

        $request = DeadlineChangeRequest::query()->sole();

        $this->assertSame(DeadlineRequestStatus::Pending, $request->status);
        $this->assertSame('2026-02-15', $request->previous_on->toDateString());
        $this->assertSame('2026-02-25', $request->proposed_on->toDateString());
        $this->assertSame($student->id, $request->requested_by);
        $this->assertSame('2026-02-15', $task->fresh()->due_on->toDateString());

        Notification::assertSentTo($client, DeadlineChangeRequested::class);
    }

    public function test_the_client_approves_and_the_deadline_moves(): void
    {
        Notification::fake();

        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $teammate = $this->teammateOf($student);
        $task = $this->task($agreement, dueOn: '2026-02-15');
        $request = $this->ask($student, $agreement, $task, '2026-02-25');

        $this->actingAs($client)
            ->post(route('agreements.deadline-requests.approve', $this->asParty($client, $agreement, ['deadlineRequest' => $request])), ['decision_note' => 'Fine by us.'])
            ->assertSessionHasNoErrors();

        $request->refresh();

        $this->assertSame('2026-02-25', $task->fresh()->due_on->toDateString());
        $this->assertSame(DeadlineRequestStatus::Approved, $request->status);
        $this->assertSame($client->id, $request->decided_by);
        $this->assertSame('Fine by us.', $request->decision_note);

        Notification::assertSentTo([$student, $teammate], DeadlineChangeDecided::class);
    }

    public function test_the_client_declines_and_the_deadline_stays(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement, dueOn: '2026-02-15');
        $request = $this->ask($student, $agreement, $task, '2026-02-25');

        $this->actingAs($client)
            ->post(route('agreements.deadline-requests.decline', $this->asParty($client, $agreement, ['deadlineRequest' => $request])), ['decision_note' => 'We need it for the demo.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-02-15', $task->fresh()->due_on->toDateString());
        $this->assertSame(DeadlineRequestStatus::Declined, $request->fresh()->status);
    }

    public function test_only_the_client_decides_and_only_the_student_side_asks(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement, dueOn: '2026-02-15');

        $this->actingAs($client)
            ->post($this->askUrl($client, $agreement, $task), ['proposed_on' => '2026-02-25'])
            ->assertForbidden();

        $request = $this->ask($student, $agreement, $task, '2026-02-25');

        $this->actingAs($student)
            ->post(route('agreements.deadline-requests.approve', $this->asParty($student, $agreement, ['deadlineRequest' => $request])))
            ->assertForbidden();

        $this->assertSame(DeadlineRequestStatus::Pending, $request->fresh()->status);
        $this->assertSame('2026-02-15', $task->fresh()->due_on->toDateString());
    }

    public function test_a_teammate_may_ask_too(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $teammate = $this->teammateOf($student);
        $task = $this->task($agreement, dueOn: '2026-02-15');

        $this->actingAs($teammate)
            ->post(route('agreements.tasks.deadline-requests.store', [
                'current_team' => $student->currentTeam,
                'agreement' => $agreement,
                'task' => $task,
            ]), ['proposed_on' => '2026-02-25'])
            ->assertSessionHasNoErrors();

        $this->assertSame($teammate->id, DeadlineChangeRequest::query()->sole()->requested_by);
    }

    public function test_an_ask_the_rules_cannot_allow_is_refused(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement, dueOn: '2026-02-15');
        $undated = $this->task($agreement, dueOn: null);
        $verified = $this->task($agreement, dueOn: '2026-02-15', status: TaskStatus::Verified);

        $ask = fn (AgreementTask $which, string $date) => $this->actingAs($student)
            ->post($this->askUrl($student, $agreement, $which), ['proposed_on' => $date]);

        $ask($task, '2026-04-13')->assertSessionHasErrors('proposed_on');
        $ask($task, '2026-02-15')->assertSessionHasErrors('proposed_on');
        $ask($undated, '2026-02-25')->assertSessionHasErrors('proposed_on');
        $ask($verified, '2026-02-25')->assertSessionHasErrors('proposed_on');

        $this->assertSame(0, DeadlineChangeRequest::query()->count());
    }

    public function test_a_deadline_carries_one_waiting_ask_at_a_time(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement, dueOn: '2026-02-15');

        $this->ask($student, $agreement, $task, '2026-02-25');

        $this->actingAs($student)
            ->post($this->askUrl($student, $agreement, $task), ['proposed_on' => '2026-02-28'])
            ->assertSessionHasErrors(['proposed_on' => 'A change to this deadline is already waiting for the client.']);

        $this->assertSame(1, DeadlineChangeRequest::query()->count());
    }

    public function test_the_student_takes_an_ask_back_but_not_an_answered_one(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement, dueOn: '2026-02-15');
        $request = $this->ask($student, $agreement, $task, '2026-02-25');
        $destroy = route('agreements.deadline-requests.destroy', $this->asParty($student, $agreement, ['deadlineRequest' => $request]));

        $this->actingAs($student)->delete($destroy)->assertSessionHasNoErrors();
        $this->assertSame(DeadlineRequestStatus::Withdrawn, $request->fresh()->status);

        $answered = $this->ask($student, $agreement, $task, '2026-02-26');
        $this->actingAs($client)->post(route('agreements.deadline-requests.decline', $this->asParty($client, $agreement, ['deadlineRequest' => $answered])));

        $this->actingAs($student)
            ->delete(route('agreements.deadline-requests.destroy', $this->asParty($student, $agreement, ['deadlineRequest' => $answered])))
            ->assertSessionHasErrors('deadline');

        $this->assertSame(DeadlineRequestStatus::Declined, $answered->fresh()->status);
    }

    public function test_an_ask_is_decided_once(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement, dueOn: '2026-02-15');
        $request = $this->ask($student, $agreement, $task, '2026-02-25');
        $params = $this->asParty($client, $agreement, ['deadlineRequest' => $request]);

        $this->actingAs($client)->post(route('agreements.deadline-requests.decline', $params))->assertSessionHasNoErrors();
        $this->actingAs($client)->post(route('agreements.deadline-requests.approve', $params))->assertSessionHasErrors('deadline');

        $this->assertSame('2026-02-15', $task->fresh()->due_on->toDateString());
    }

    public function test_another_agreements_ask_does_not_resolve(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration();
        ['student' => $otherStudent, 'agreement' => $other] = $this->collaboration();
        $request = $this->ask($otherStudent, $other, $this->task($other, dueOn: '2026-02-15'), '2026-02-25');

        $this->actingAs($client)
            ->post(route('agreements.deadline-requests.approve', $this->asParty($client, $agreement, ['deadlineRequest' => $request])))
            ->assertNotFound();

        $this->assertSame(DeadlineRequestStatus::Pending, $request->fresh()->status);
    }

    public function test_the_final_deadline_moves_only_through_the_client(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $turnover = $agreement->milestones[2];

        /* Dragging it on the timeline is refused. */
        $this->actingAs($student)
            ->patch(route('agreements.milestones.schedule', $this->asParty($student, $agreement, ['milestone' => $turnover])), [
                'starts_on' => '2026-03-30',
                'ends_on' => '2026-04-30',
            ])
            ->assertSessionHasErrors('ends_on');

        $this->actingAs($student)
            ->post(route('agreements.milestones.deadline-requests.store', $this->asParty($student, $agreement, ['milestone' => $turnover])), [
                'proposed_on' => '2026-04-30',
                'reason' => 'Two extra weeks for user testing.',
            ])
            ->assertSessionHasNoErrors();

        $request = DeadlineChangeRequest::query()->sole();
        $this->assertTrue($request->isForFinalDeadline());
        $this->assertSame('2026-04-12', $turnover->fresh()->scheduledEndsOn()->toDateString());

        $this->actingAs($client)
            ->post(route('agreements.deadline-requests.approve', $this->asParty($client, $agreement, ['deadlineRequest' => $request])))
            ->assertSessionHasNoErrors();

        $turnover->refresh();
        $this->assertSame('2026-04-30', $turnover->scheduledEndsOn()->toDateString());
        // What was signed stays as signed.
        $this->assertSame('2026-04-12', $turnover->ends_on->toDateString());
        $this->assertSame('2026-04-30', $agreement->fresh()->load('milestones')->finalDeadline()->toDateString());
    }

    public function test_the_final_deadline_cannot_come_before_a_task_or_move_on_another_phase(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        [$design, , $turnover] = $agreement->milestones;
        $this->task($agreement, dueOn: '2026-04-10');

        $this->actingAs($student)
            ->post(route('agreements.milestones.deadline-requests.store', $this->asParty($student, $agreement, ['milestone' => $turnover])), ['proposed_on' => '2026-04-05'])
            ->assertSessionHasErrors(['proposed_on' => 'A task is due on 10 Apr 2026, so the final deadline cannot come before it.']);

        $this->actingAs($student)
            ->post(route('agreements.milestones.deadline-requests.store', $this->asParty($student, $agreement, ['milestone' => $design])), ['proposed_on' => '2026-03-01'])
            ->assertSessionHasErrors('proposed_on');

        $this->assertSame(0, DeadlineChangeRequest::query()->count());
    }

    public function test_design_and_build_may_overlap_but_turnover_may_not(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        [$design, $build, $turnover] = $agreement->milestones;
        $schedule = fn ($phase, string $startsOn, string $endsOn) => $this->actingAs($student)
            ->patch(route('agreements.milestones.schedule', $this->asParty($student, $agreement, ['milestone' => $phase])), [
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
            ]);

        /* Build starts before Design ends: allowed. */
        $schedule($build, '2026-02-15', '2026-03-20')->assertSessionHasNoErrors();

        /* Build running into Turnover: refused. */
        $schedule($build, '2026-02-15', '2026-03-30')
            ->assertSessionHasErrors(['ends_on' => 'This phase has to end before Turnover starts on 30 Mar 2026.']);

        /* Turnover starting before Build ends: refused. */
        $schedule($turnover, '2026-03-15', '2026-04-12')
            ->assertSessionHasErrors(['starts_on' => 'Turnover cannot overlap the other phases. Start it after 20 Mar 2026.']);

        /* Turnover moving its own start, the end left where it is: allowed. */
        $schedule($turnover, '2026-03-25', '2026-04-12')->assertSessionHasNoErrors();

        $this->assertSame('2026-03-20', $build->fresh()->scheduledEndsOn()->toDateString());
        $this->assertSame('2026-03-25', $turnover->fresh()->scheduledStartsOn()->toDateString());
        $this->assertSame('2026-02-22', $design->fresh()->scheduledEndsOn()->toDateString());
    }

    public function test_the_screen_shows_deadlines_and_the_ask_waiting_on_them(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement, dueOn: '2026-02-15');
        $request = $this->ask($student, $agreement, $task, '2026-02-25');

        $this->actingAs($client)
            ->get(route('project-management', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn ($page) => $page
                ->where('agreement.finalDeadline', '2026-04-12')
                ->where('agreement.summary.finalDeadline', '2026-04-12')
                ->where('agreement.phases.0.isTurnover', false)
                ->where('agreement.phases.2.isTurnover', true)
                ->where('agreement.phases.0.tasks.0.dueOn', '2026-02-15')
                ->where('agreement.phases.0.tasks.0.deadlineRequest.id', $request->id)
                ->where('agreement.phases.0.tasks.0.deadlineRequest.status', 'pending')
                ->where('agreement.phases.0.tasks.0.deadlineRequest.proposedOn', '2026-02-25')
                ->where('agreement.finalDeadlineRequest', null));
    }

    public function test_both_calendars_show_task_deadlines_and_asked_for_dates(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement, dueOn: '2026-02-15');
        $this->ask($student, $agreement, $task, '2026-02-25');

        $this->actingAs($client)
            ->get(route('client.dashboard', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn ($page) => $page->loadDeferredProps(function ($reload) {
                $events = collect($reload->toArray()['props']['calendarEvents']);

                $this->assertTrue($events->contains(fn (array $event): bool => $event['kind'] === 'task' && $event['date'] === '2026-02-15'));
                $this->assertTrue($events->contains(fn (array $event): bool => $event['kind'] === 'request' && $event['date'] === '2026-02-25'));
            }));

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn ($page) => $page
                ->where('calendar.marks.2026-02-15', $task->title.' due')
                ->where('calendar.marks.2026-02-25', $task->title.' (new date asked for)'));
    }

    public function test_both_sides_read_the_ask_and_the_answer_in_the_bell(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement, dueOn: '2026-02-15');
        $request = $this->ask($student, $agreement, $task, '2026-02-25');

        $asked = app(PresentNotification::class)->handle($client->notifications()->where('type', DeadlineChangeRequested::class)->sole(), $client->currentTeam);

        $this->assertSame($student->name.' asked to move the deadline for "'.$task->title.'"', $asked['title']);
        $this->assertStringContainsString('agreement='.$agreement->id, (string) $asked['url']);

        $this->actingAs($client)->post(route('agreements.deadline-requests.approve', $this->asParty($client, $agreement, ['deadlineRequest' => $request])));

        $answered = app(PresentNotification::class)->handle($student->notifications()->where('type', DeadlineChangeDecided::class)->sole(), $student->currentTeam);

        $this->assertSame('The client approved moving the deadline for "'.$task->title.'"', $answered['title']);
    }

    private function askUrl(User $user, Agreement $agreement, AgreementTask $task): string
    {
        return route('agreements.tasks.deadline-requests.store', $this->asParty($user, $agreement, ['task' => $task]));
    }

    private function ask(User $student, Agreement $agreement, AgreementTask $task, string $proposedOn): DeadlineChangeRequest
    {
        $this->actingAs($student)
            ->post($this->askUrl($student, $agreement, $task), ['proposed_on' => $proposedOn])
            ->assertSessionHasNoErrors();

        return DeadlineChangeRequest::query()->latest('id')->firstOrFail();
    }

    private function task(Agreement $agreement, ?string $dueOn, TaskStatus $status = TaskStatus::Open): AgreementTask
    {
        return AgreementTask::factory()->create([
            'agreement_milestone_id' => $agreement->milestones->first()->id,
            'due_on' => $dueOn,
            'status' => $status,
        ]);
    }

    private function teammateOf(User $student): User
    {
        $teammate = User::factory()->student()->approved()->create();
        $student->currentTeam->members()->attach($teammate, ['role' => TeamRole::Member->value]);
        $teammate->switchTeam($student->currentTeam);

        return $teammate->refresh();
    }
}
