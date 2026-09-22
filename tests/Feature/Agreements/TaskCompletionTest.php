<?php

namespace Tests\Feature\Agreements;

use App\Enums\AgreementStatus;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Http\Requests\Agreements\SubmitTaskRequest;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\AgreementTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Agreements\Concerns\StartsCollaboration;
use Tests\TestCase;

/**
 * Granular Task Completion: who may do what to a task.
 *
 * The student is the updater — writes the checklist, checks tasks off with
 * proof, plans the timeline. The client is the verifier — the only one who can
 * mark a task done. Every rule here is enforced by the server; the screen only
 * hides buttons.
 */
class TaskCompletionTest extends TestCase
{
    use RefreshDatabase, StartsCollaboration;

    public function test_the_student_adds_tasks_to_the_end_of_a_phase(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $design = $agreement->milestones->first();

        foreach (['UI wireframes', 'Database schema'] as $title) {
            $this->actingAs($student)
                ->post(route('agreements.tasks.store', $this->asParty($student, $agreement, ['milestone' => $design])), ['title' => $title, 'due_on' => '2026-02-20'])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(
            [['UI wireframes', 1], ['Database schema', 2]],
            $design->tasks()->get()->map(fn (AgreementTask $task): array => [$task->title, $task->position])->all(),
        );
        $this->assertSame(TaskStatus::Open, $design->tasks()->first()->status);
    }

    public function test_the_client_cannot_write_the_checklist_or_move_the_timeline(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration();
        $design = $agreement->milestones->first();
        $task = AgreementTask::factory()->create(['agreement_milestone_id' => $design->id]);

        $asClient = fn (array $extra) => $this->asParty($client, $agreement, $extra);

        $this->actingAs($client)->post(route('agreements.tasks.store', $asClient(['milestone' => $design])), ['title' => 'Mine'])->assertForbidden();
        $this->actingAs($client)->patch(route('agreements.tasks.update', $asClient(['task' => $task])), ['title' => 'Renamed'])->assertForbidden();
        $this->actingAs($client)->delete(route('agreements.tasks.destroy', $asClient(['task' => $task])))->assertForbidden();
        $this->actingAs($client)->put(route('agreements.tasks.reorder', $asClient(['milestone' => $design])), ['task_ids' => [$task->id]])->assertForbidden();
        $this->actingAs($client)->post(route('agreements.tasks.submit', $asClient(['task' => $task])), ['proof_note' => 'Done'])->assertForbidden();
        $this->actingAs($client)->patch(route('agreements.milestones.schedule', $asClient(['milestone' => $design])), [
            'starts_on' => '2026-02-01',
            'ends_on' => '2026-02-10',
        ])->assertForbidden();

        $this->assertSame(1, $design->tasks()->count());
        $this->assertSame(TaskStatus::Open, $task->refresh()->status);
        $this->assertNull($design->refresh()->planned_starts_on);
    }

    public function test_the_student_cannot_verify_or_send_back_their_own_work(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement, TaskStatus::Submitted);

        $this->actingAs($student)
            ->post(route('agreements.tasks.verify', $this->asParty($student, $agreement, ['task' => $task])))
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('agreements.tasks.send-back', $this->asParty($student, $agreement, ['task' => $task])), ['review_note' => 'No'])
            ->assertForbidden();

        $this->assertSame(TaskStatus::Submitted, $task->refresh()->status);
    }

    public function test_checking_a_task_off_hands_it_over_without_completing_it(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement);

        $this->actingAs($student)
            ->post(route('agreements.tasks.submit', $this->asParty($student, $agreement, ['task' => $task])), [
                'proof_note' => 'Wireframes reviewed in Monday consult.',
                'proof_url' => 'https://figma.com/file/abc',
            ])
            ->assertSessionHasNoErrors();

        $task->refresh();

        $this->assertSame(TaskStatus::Submitted, $task->status);
        $this->assertSame($student->id, $task->submitted_by);
        $this->assertSame('https://figma.com/file/abc', $task->proof_url);
        $this->assertSame(0, $agreement->fresh()->progress());
    }

    public function test_checking_a_task_off_needs_some_proof(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement);

        $this->actingAs($student)
            ->post(route('agreements.tasks.submit', $this->asParty($student, $agreement, ['task' => $task])), [])
            ->assertSessionHasErrors('proof_note');

        $this->actingAs($student)
            ->post(route('agreements.tasks.submit', $this->asParty($student, $agreement, ['task' => $task])), ['proof_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('proof_url');

        $this->assertSame(TaskStatus::Open, $task->refresh()->status);
    }

    public function test_the_client_verifies_a_submitted_task(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement, TaskStatus::Submitted);

        $this->actingAs($client)
            ->post(route('agreements.tasks.verify', $this->asParty($client, $agreement, ['task' => $task])))
            ->assertSessionHasNoErrors();

        $task->refresh();

        $this->assertSame(TaskStatus::Verified, $task->status);
        $this->assertSame($client->id, $task->verified_by);
        $this->assertNotNull($task->verified_at);
    }

    public function test_a_task_nobody_submitted_cannot_be_verified(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement);

        $this->actingAs($client)
            ->post(route('agreements.tasks.verify', $this->asParty($client, $agreement, ['task' => $task])))
            ->assertSessionHasErrors('task');

        $this->assertSame(TaskStatus::Open, $task->refresh()->status);
    }

    public function test_the_client_sends_work_back_with_a_reason(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement, TaskStatus::Submitted);
        $sendBack = route('agreements.tasks.send-back', $this->asParty($client, $agreement, ['task' => $task]));

        $this->actingAs($client)->post($sendBack, ['review_note' => ''])->assertSessionHasErrors('review_note');

        $this->actingAs($client)->post($sendBack, ['review_note' => 'Add the admin role too.'])->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame(TaskStatus::Open, $task->status);
        $this->assertSame('Add the admin role too.', $task->review_note);

        // Resubmitting answers the note, so it clears.
        $this->actingAs($student)
            ->post(route('agreements.tasks.submit', $this->asParty($student, $agreement, ['task' => $task])), ['proof_note' => 'Admin role added.'])
            ->assertSessionHasNoErrors();

        $this->assertNull($task->refresh()->review_note);
    }

    public function test_the_student_can_withdraw_a_task_before_it_is_reviewed(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement, TaskStatus::Submitted);

        $this->actingAs($student)
            ->delete(route('agreements.tasks.withdraw', $this->asParty($student, $agreement, ['task' => $task])))
            ->assertSessionHasNoErrors();

        $this->assertSame(TaskStatus::Open, $task->refresh()->status);
    }

    public function test_verified_work_can_no_longer_be_edited_deleted_or_withdrawn(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement, TaskStatus::Verified);
        $params = $this->asParty($student, $agreement, ['task' => $task]);

        $this->actingAs($student)->patch(route('agreements.tasks.update', $params), ['title' => 'Rewritten'])->assertSessionHasErrors('task');
        $this->actingAs($student)->delete(route('agreements.tasks.destroy', $params))->assertSessionHasErrors('task');
        $this->actingAs($student)->delete(route('agreements.tasks.withdraw', $params))->assertSessionHasErrors('task');

        $this->assertModelExists($task);
        $this->assertSame(TaskStatus::Verified, $task->refresh()->status);
    }

    public function test_a_stranger_is_refused_everything(): void
    {
        ['agreement' => $agreement] = $this->collaboration();
        $stranger = User::factory()->student()->approved()->create();
        $task = $this->task($agreement, TaskStatus::Submitted);
        $params = $this->asParty($stranger, $agreement, ['task' => $task]);

        $this->actingAs($stranger)->post(route('agreements.tasks.store', $this->asParty($stranger, $agreement, ['milestone' => $agreement->milestones->first()])), ['title' => 'x'])->assertForbidden();
        $this->actingAs($stranger)->post(route('agreements.tasks.verify', $params))->assertForbidden();
        $this->actingAs($stranger)->delete(route('agreements.tasks.withdraw', $params))->assertForbidden();
        $this->actingAs($stranger)->get(route('agreements.tasks.proof', $params))->assertForbidden();
    }

    public function test_nothing_can_be_written_before_the_agreement_is_signed(): void
    {
        ['student' => $student, 'client' => $client, 'agreement' => $agreement] = $this->collaboration(AgreementStatus::AwaitingSignatures);
        $design = $agreement->milestones->first();
        $task = $this->task($agreement, TaskStatus::Submitted);

        $this->actingAs($student)
            ->post(route('agreements.tasks.store', $this->asParty($student, $agreement, ['milestone' => $design])), ['title' => 'Early'])
            ->assertForbidden();

        $this->actingAs($client)
            ->post(route('agreements.tasks.verify', $this->asParty($client, $agreement, ['task' => $task])))
            ->assertForbidden();

        $this->actingAs($student)
            ->patch(route('agreements.milestones.schedule', $this->asParty($student, $agreement, ['milestone' => $design])), [
                'starts_on' => '2026-02-01',
                'ends_on' => '2026-02-10',
            ])
            ->assertForbidden();
    }

    public function test_a_task_from_another_agreement_is_not_found(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();

        // The same student's other contract: authorised, but not this URL's.
        $other = Agreement::factory()->active()->create([
            'project_id' => $agreement->project_id,
            'application_id' => $agreement->application_id,
            'team_id' => $agreement->team_id,
            'student_id' => $student->id,
            'version' => 2,
        ]);
        $otherPhase = AgreementMilestone::factory()->create(['agreement_id' => $other->id]);
        $stray = AgreementTask::factory()->create(['agreement_milestone_id' => $otherPhase->id]);

        $this->actingAs($student)
            ->patch(route('agreements.tasks.update', $this->asParty($student, $agreement, ['task' => $stray])), ['title' => 'Hijacked'])
            ->assertNotFound();

        $this->actingAs($student)
            ->post(route('agreements.tasks.store', $this->asParty($student, $agreement, ['milestone' => $otherPhase])), ['title' => 'Hijacked'])
            ->assertNotFound();

        $this->assertNotSame('Hijacked', $stray->refresh()->title);
    }

    public function test_the_students_teammate_shares_the_work_but_not_the_clients_moves(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $teammate = User::factory()->student()->approved()->create();
        $student->currentTeam->members()->attach($teammate, ['role' => TeamRole::Member->value]);
        $teammate->switchTeam($student->currentTeam);
        $task = $this->task($agreement);
        $asTeammate = fn (array $extra) => ['current_team' => $student->currentTeam, 'agreement' => $agreement, ...$extra];

        $this->actingAs($teammate)
            ->get(route('project-management', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('agreement.id', $agreement->id)
                ->where('can.manage', true)
                ->where('can.verify', false)
                ->where('can.complete', false));

        $this->actingAs($teammate)
            ->post(route('agreements.tasks.submit', $asTeammate(['task' => $task])), ['proof_note' => 'Done'])
            ->assertSessionHasNoErrors();

        $this->assertSame(TaskStatus::Submitted, $task->refresh()->status);
        $this->assertSame($teammate->id, $task->submitted_by);

        /* Checking the work stays the client's alone. */
        $this->actingAs($teammate)
            ->post(route('agreements.tasks.verify', $asTeammate(['task' => $task])))
            ->assertForbidden();

        $this->assertSame(TaskStatus::Submitted, $task->refresh()->status);
    }

    public function test_someone_on_no_team_of_the_student_can_do_nothing(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $stranger = User::factory()->student()->approved()->create();
        $task = $this->task($agreement);

        $this->actingAs($stranger)
            ->post(route('agreements.tasks.submit', ['current_team' => $stranger->currentTeam, 'agreement' => $agreement, 'task' => $task]), ['proof_note' => 'Done'])
            ->assertForbidden();

        $this->assertSame(TaskStatus::Open, $task->refresh()->status);
    }

    public function test_reordering_takes_exactly_the_phases_tasks(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $design = $agreement->milestones->first();
        $first = AgreementTask::factory()->create(['agreement_milestone_id' => $design->id, 'position' => 1]);
        $second = AgreementTask::factory()->create(['agreement_milestone_id' => $design->id, 'position' => 2]);
        $elsewhere = AgreementTask::factory()->create(['agreement_milestone_id' => $agreement->milestones[1]->id]);
        $url = route('agreements.tasks.reorder', $this->asParty($student, $agreement, ['milestone' => $design]));

        $this->actingAs($student)->put($url, ['task_ids' => [$second->id, $elsewhere->id]])->assertSessionHasErrors('task_ids');
        $this->actingAs($student)->put($url, ['task_ids' => [$second->id]])->assertSessionHasErrors('task_ids');

        $this->actingAs($student)->put($url, ['task_ids' => [$second->id, $first->id]])->assertSessionHasNoErrors();

        $this->assertSame([$second->id, $first->id], $design->tasks()->pluck('id')->all());
    }

    public function test_the_student_plans_phase_dates_without_touching_the_signed_ones(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $build = $agreement->milestones[1];
        $url = route('agreements.milestones.schedule', $this->asParty($student, $agreement, ['milestone' => $build]));

        $this->actingAs($student)
            ->patch($url, ['starts_on' => '2026-03-10', 'ends_on' => '2026-03-01'])
            ->assertSessionHasErrors('ends_on');

        $this->actingAs($student)
            ->patch($url, ['starts_on' => '2026-03-01', 'ends_on' => '2026-03-27'])
            ->assertSessionHasNoErrors();

        $build->refresh();

        $this->assertSame('2026-03-01', $build->planned_starts_on->toDateString());
        $this->assertSame('2026-03-27', $build->scheduledEndsOn()->toDateString());
        // What was signed stays as signed.
        $this->assertSame('2026-02-23', $build->starts_on->toDateString());
        $this->assertSame('2026-03-29', $build->ends_on->toDateString());
    }

    public function test_a_proof_file_is_stored_privately_and_served_only_to_the_project(): void
    {
        Storage::fake(AgreementTask::PROOF_DISK);

        ['student' => $student, 'client' => $client, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement);

        $this->actingAs($student)
            ->post(route('agreements.tasks.submit', $this->asParty($student, $agreement, ['task' => $task])), [
                'proof_file' => UploadedFile::fake()->image('wireframes.png'),
            ])
            ->assertSessionHasNoErrors();

        $task->refresh();

        $this->assertSame('wireframes.png', $task->proof_name);
        $this->assertStringStartsWith('task-proofs/'.$agreement->id.'/', $task->proof_path);
        Storage::disk(AgreementTask::PROOF_DISK)->assertExists($task->proof_path);

        $response = $this->actingAs($client)
            ->get(route('agreements.tasks.proof', $this->asParty($client, $agreement, ['task' => $task])))
            ->assertOk();

        // Never held by a shared cache, which would skip the check for the next reader.
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));

        $this->actingAs($student)
            ->get(route('agreements.tasks.proof', $this->asParty($student, $agreement, ['task' => $task])))
            ->assertOk();

        $stranger = User::factory()->client()->create();

        $this->actingAs($stranger)
            ->get(route('agreements.tasks.proof', $this->asParty($stranger, $agreement, ['task' => $task])))
            ->assertForbidden();
    }

    public function test_a_proof_file_must_be_an_image_or_pdf_of_at_most_ten_megabytes(): void
    {
        Storage::fake(AgreementTask::PROOF_DISK);

        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement);
        $url = route('agreements.tasks.submit', $this->asParty($student, $agreement, ['task' => $task]));

        $this->actingAs($student)
            ->post($url, ['proof_file' => UploadedFile::fake()->create('build.exe', 100, 'application/x-msdownload')])
            ->assertSessionHasErrors('proof_file');

        $this->actingAs($student)
            ->post($url, ['proof_file' => UploadedFile::fake()->create('report.pdf', SubmitTaskRequest::MAX_FILE_KILOBYTES + 1, 'application/pdf')])
            ->assertSessionHasErrors('proof_file');

        $this->actingAs($student)
            ->post($url, ['proof_file' => UploadedFile::fake()->create('report.pdf', 500, 'application/pdf')])
            ->assertSessionHasNoErrors();

        $this->assertSame(TaskStatus::Submitted, $task->refresh()->status);
    }

    public function test_replacing_or_deleting_proof_removes_the_old_file(): void
    {
        Storage::fake(AgreementTask::PROOF_DISK);

        ['student' => $student, 'client' => $client, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement);
        $params = $this->asParty($student, $agreement, ['task' => $task]);

        $this->actingAs($student)->post(route('agreements.tasks.submit', $params), ['proof_file' => UploadedFile::fake()->image('first.png')]);
        $firstPath = $task->refresh()->proof_path;

        // Sent back, then resubmitted with a new screenshot.
        $this->actingAs($client)->post(route('agreements.tasks.send-back', $this->asParty($client, $agreement, ['task' => $task])), ['review_note' => 'Blurry']);
        $this->actingAs($student)->post(route('agreements.tasks.submit', $params), ['proof_file' => UploadedFile::fake()->image('second.png')]);

        $secondPath = $task->refresh()->proof_path;

        Storage::disk(AgreementTask::PROOF_DISK)->assertMissing($firstPath);
        Storage::disk(AgreementTask::PROOF_DISK)->assertExists($secondPath);

        $this->actingAs($student)->delete(route('agreements.tasks.withdraw', $params));
        $this->actingAs($student)->delete(route('agreements.tasks.destroy', $params))->assertSessionHasNoErrors();

        $this->assertModelMissing($task);
        Storage::disk(AgreementTask::PROOF_DISK)->assertMissing($secondPath);
    }

    public function test_a_resubmission_can_keep_the_file_already_attached(): void
    {
        Storage::fake(AgreementTask::PROOF_DISK);

        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = $this->task($agreement);
        $params = $this->asParty($student, $agreement, ['task' => $task]);

        $this->actingAs($student)->post(route('agreements.tasks.submit', $params), ['proof_file' => UploadedFile::fake()->image('shot.png')]);
        $this->actingAs($student)->delete(route('agreements.tasks.withdraw', $params));

        $this->actingAs($student)->post(route('agreements.tasks.submit', $params), [])->assertSessionHasNoErrors();
        $this->actingAs($student)->delete(route('agreements.tasks.withdraw', $params));

        // Removing the only proof leaves nothing to submit with.
        $this->actingAs($student)->post(route('agreements.tasks.submit', $params), ['remove_file' => true])->assertSessionHasErrors('proof_note');
    }

    /**
     * A task in the first phase of the agreement.
     */
    private function task(Agreement $agreement, TaskStatus $status = TaskStatus::Open): AgreementTask
    {
        return AgreementTask::factory()->create([
            'agreement_milestone_id' => $agreement->milestones->first()->id,
            'status' => $status,
        ]);
    }
}
