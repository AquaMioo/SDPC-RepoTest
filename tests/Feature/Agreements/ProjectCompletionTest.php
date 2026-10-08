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
use App\Models\ProjectRating;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\Agreements\ProjectCompleted;
use App\Notifications\Client\ProjectStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use LogicException;
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

    /** Completing is rating (owner, 2026-10-09). */
    private const RATING = ['rating' => 5, 'feedback' => 'Delivered as agreed.'];

    public function test_the_client_completes_the_project_and_its_agreement(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration();

        $this->actingAs($client)
            ->post($this->completeUrl($client, $agreement), self::RATING)
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

        $this->actingAs($client)->post($this->completeUrl($client, $agreement), self::RATING);

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

        $this->actingAs($client)->post($this->completeUrl($client, $agreement), self::RATING);

        $this->assertSame(AgreementStatus::Completed, $second->fresh()->status);
    }

    public function test_the_student_side_and_the_business_are_told(): void
    {
        Notification::fake();

        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $teammate = $this->teammateOf($student);

        $this->actingAs($client)->post($this->completeUrl($client, $agreement), self::RATING);

        Notification::assertSentTo([$student, $teammate], ProjectCompleted::class);
        Notification::assertSentTo($client, ProjectStatusChanged::class);
        Notification::assertNotSentTo($client, ProjectCompleted::class);
    }

    public function test_the_completed_notification_reads_in_the_bell(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();

        $this->actingAs($client)->post($this->completeUrl($client, $agreement), self::RATING);

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

        $this->actingAs($student)->post($this->completeUrl($student, $agreement), self::RATING)->assertForbidden();
        $this->actingAs($teammate)->post(route('agreements.completion.store', [
            'current_team' => $student->currentTeam,
            'agreement' => $agreement,
        ]))->assertForbidden();
        $this->actingAs($otherClient)->post($this->completeUrl($otherClient, $agreement), self::RATING)->assertForbidden();

        $this->assertSame(AgreementStatus::Active, $agreement->fresh()->status);
        $this->assertSame(ProjectStatus::InProgress, $agreement->project->fresh()->status);
    }

    public function test_an_agreement_nobody_has_signed_cannot_be_completed(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration(AgreementStatus::AwaitingSignatures);

        $this->actingAs($client)->post($this->completeUrl($client, $agreement), self::RATING)->assertForbidden();

        $this->assertSame(AgreementStatus::AwaitingSignatures, $agreement->fresh()->status);
    }

    public function test_completing_twice_changes_nothing_the_second_time(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration();

        $this->actingAs($client)->post($this->completeUrl($client, $agreement), self::RATING)->assertSessionHasNoErrors();
        $completedAt = $agreement->fresh()->completed_at;

        $this->actingAs($client)->post($this->completeUrl($client, $agreement), self::RATING)->assertForbidden();

        $this->assertEquals($completedAt, $agreement->fresh()->completed_at);
    }

    public function test_a_project_that_is_not_in_progress_is_refused(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration();
        $agreement->project->update(['status' => ProjectStatus::Closed]);

        $this->actingAs($client)
            ->post($this->completeUrl($client, $agreement), self::RATING)
            ->assertSessionHasErrors('project');

        $this->assertSame(AgreementStatus::Active, $agreement->fresh()->status);
    }

    public function test_the_migration_puts_knocked_out_builds_back_in_progress_so_they_can_be_completed(): void
    {
        ['client' => $client, 'agreement' => $archived] = $this->collaboration();
        ['agreement' => $reopened] = $this->collaboration();
        ['agreement' => $negotiating] = $this->collaboration(AgreementStatus::AwaitingSignatures);

        // The three buttons that used to reach a running build.
        $archived->project->update(['status' => ProjectStatus::Archived]);
        $reopened->project->update(['status' => ProjectStatus::Open]);
        $negotiating->project->update(['status' => ProjectStatus::Open]);
        $delivered = Project::factory()->completed()->create();

        $migration = require database_path('migrations/2026_10_04_015204_restore_in_progress_to_projects_with_an_active_agreement.php');
        $migration->up();

        $this->assertSame(ProjectStatus::InProgress, $archived->project->fresh()->status);
        $this->assertSame(ProjectStatus::InProgress, $reopened->project->fresh()->status);
        // No signed agreement yet, so it is still a posting.
        $this->assertSame(ProjectStatus::Open, $negotiating->project->fresh()->status);
        $this->assertSame(ProjectStatus::Completed, $delivered->fresh()->status);

        $this->actingAs($client)
            ->post($this->completeUrl($client, $archived), self::RATING)
            ->assertSessionHasNoErrors();

        $this->assertSame(ProjectStatus::Completed, $archived->project->fresh()->status);
    }

    public function test_the_client_may_complete_with_tasks_still_unverified(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration();
        AgreementTask::factory()->create([
            'agreement_milestone_id' => $agreement->milestones->first()->id,
            'status' => TaskStatus::Submitted,
        ]);

        $this->actingAs($client)->post($this->completeUrl($client, $agreement), self::RATING)->assertSessionHasNoErrors();

        $this->assertSame(AgreementStatus::Completed, $agreement->fresh()->status);
    }

    public function test_a_completed_build_stays_readable_but_nothing_can_be_written(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $task = AgreementTask::factory()->create(['agreement_milestone_id' => $agreement->milestones->first()->id]);

        $this->actingAs($client)->post($this->completeUrl($client, $agreement), self::RATING);

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

    public function test_completing_needs_a_rating_from_one_to_five(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration();

        foreach ([[], ['rating' => 0], ['rating' => 6], ['rating' => 'great']] as $payload) {
            $this->actingAs($client)
                ->post($this->completeUrl($client, $agreement), $payload)
                ->assertSessionHasErrors('rating');
        }

        $this->assertSame(ProjectStatus::InProgress, $agreement->project->fresh()->status);
        $this->assertSame(0, ProjectRating::query()->count());
    }

    /**
     * The rating goes to the signer and every teammate, permanently, and
     * counts the build on each of their profiles (owner, 2026-10-09).
     */
    public function test_the_rating_is_written_for_the_signer_and_teammates_and_is_permanent(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $teammate = $this->teammateOf($student);
        StudentProfile::factory()->for($student)->create();
        StudentProfile::factory()->for($teammate)->create();

        $this->actingAs($client)
            ->post($this->completeUrl($client, $agreement), ['rating' => 4, 'feedback' => 'Clean code and on time.'])
            ->assertSessionHasNoErrors();

        $ratings = ProjectRating::query()->orderBy('student_id')->get();
        $this->assertEqualsCanonicalizing([$student->id, $teammate->id], $ratings->pluck('student_id')->all());
        $this->assertSame([4, 4], $ratings->pluck('rating')->all());
        $this->assertSame('Clean code and on time.', $ratings->first()->feedback);
        $this->assertSame($agreement->project->title, $ratings->first()->project_title);
        $this->assertSame($client->id, $ratings->first()->rated_by);

        foreach ([$student, $teammate] as $member) {
            $profile = $member->studentProfile()->first();
            $this->assertSame(1, $profile->completed_projects_count);
            $this->assertSame(1, $profile->ratings_count);
            $this->assertSame('4.00', $profile->rating_average);
        }

        $this->expectException(LogicException::class);
        $ratings->first()->update(['rating' => 1]);
    }

    public function test_the_rating_and_feedback_show_on_both_profiles(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        StudentProfile::factory()->for($student)->create();

        $this->actingAs($client)
            ->post($this->completeUrl($client, $agreement), ['rating' => 5, 'feedback' => 'Would hire again.']);

        /* The client has finished a project now, so the student stays visible. */
        $this->actingAs($client)
            ->get(route('students.show', ['current_team' => $client->currentTeam, 'user' => $student]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('student.rating', 5)
                ->where('student.ratingCount', 1)
                ->where('student.reviews.0.rating', 5)
                ->where('student.reviews.0.feedback', 'Would hire again.')
                ->where('student.reviews.0.projectTitle', $agreement->project->title));

        $this->actingAs($student)
            ->get(route('student.profile.edit', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn ($page) => $page
                ->where('profile.ratingCount', 1)
                ->where('profile.reviews.0.feedback', 'Would hire again.'));
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
