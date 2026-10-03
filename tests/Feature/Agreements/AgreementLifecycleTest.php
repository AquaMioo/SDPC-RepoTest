<?php

namespace Tests\Feature\Agreements;

use App\Actions\Agreements\DraftAgreement;
use App\Actions\Client\RespondToApplication;
use App\Actions\Student\RespondToInvitation;
use App\Enums\AgreementStatus;
use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Models\Application;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acceptance drafts a contract; it no longer starts the work.
 */
class AgreementLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepting_a_student_drafts_an_agreement(): void
    {
        [$owner, $project, $application] = $this->pendingApplication();

        app(RespondToApplication::class)
            ->handle($application, ApplicationStatus::Accepted, $owner);

        $agreement = $application->refresh()->agreement;

        $this->assertNotNull($agreement);
        $this->assertSame(AgreementStatus::Draft, $agreement->status);
        $this->assertSame($project->id, $agreement->project_id);
        $this->assertSame($application->user_id, $agreement->student_id);
    }

    public function test_the_posting_stays_open_until_the_agreement_is_signed(): void
    {
        [$owner, $project, $application] = $this->pendingApplication();

        app(RespondToApplication::class)
            ->handle($application, ApplicationStatus::Accepted, $owner);

        $this->assertSame(ProjectStatus::Open, $project->refresh()->status);
    }

    public function test_the_draft_carries_the_briefs_objectives_across_as_deliverables(): void
    {
        [$owner, , $application] = $this->pendingApplication([
            'objectives' => "Stock and supplier modules\nRole-based access\n\nForecast dashboard",
        ]);

        app(RespondToApplication::class)
            ->handle($application, ApplicationStatus::Accepted, $owner);

        // The client's own words, blank lines dropped — nothing invented.
        $this->assertSame([
            'Stock and supplier modules',
            'Role-based access',
            'Forecast dashboard',
        ], $application->refresh()->agreement->deliverables);
    }

    public function test_a_draft_starts_with_an_unpriced_milestone_structure(): void
    {
        [$owner, , $application] = $this->pendingApplication();

        app(RespondToApplication::class)
            ->handle($application, ApplicationStatus::Accepted, $owner);

        $milestones = $application->refresh()->agreement->milestones;

        $this->assertCount(count(config('agreements.default_milestones')), $milestones);
        $this->assertSame(0, $milestones->sum('amount'));
        $this->assertTrue($milestones->every(
            fn ($milestone): bool => $milestone->status === MilestoneStatus::Pending,
        ));
    }

    public function test_drafting_twice_returns_the_same_agreement(): void
    {
        [, , $application] = $this->pendingApplication();

        $first = app(DraftAgreement::class)->handle($application);
        $second = app(DraftAgreement::class)->handle($application->refresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $application->agreements()->count());
    }

    public function test_references_are_sequential_within_the_year(): void
    {
        [, , $first] = $this->pendingApplication();
        [, , $second] = $this->pendingApplication();

        $one = app(DraftAgreement::class)->handle($first);
        $two = app(DraftAgreement::class)->handle($second);

        $this->assertNotSame($one->reference, $two->reference);
        $this->assertStringStartsWith('SDPC-'.now()->year.'-', $one->reference);
    }

    /**
     * Deleting an account deletes its agreements outright, which leaves gaps.
     * The next reference goes past the highest still in use: with only 002
     * left, counting gave 002 again and every acceptance failed with a 500
     * on the unique (reference, version) index (sdpc.tech, 2026-10-03).
     */
    public function test_a_gap_left_by_a_deleted_agreement_is_never_reused(): void
    {
        $year = now()->year;

        [$owner, , $first] = $this->pendingApplication();
        [, , $second] = $this->pendingApplication();
        [, , $third] = $this->pendingApplication();

        $one = app(DraftAgreement::class)->handle($first);
        $two = app(DraftAgreement::class)->handle($second);
        $this->assertSame("SDPC-{$year}-001", $one->reference);
        $this->assertSame("SDPC-{$year}-002", $two->reference);

        /* What deleting the first account does: the row is gone, not trashed. */
        $one->forceDelete();

        app(RespondToApplication::class)->handle($third, ApplicationStatus::Accepted, $owner->fresh());

        $this->assertSame("SDPC-{$year}-003", $third->refresh()->agreement->reference);
    }

    /**
     * The student accepting a client's invitation drafts through the same
     * door, so it must get past the same gap.
     */
    public function test_a_student_accepting_an_invitation_after_a_deleted_agreement_gets_a_fresh_reference(): void
    {
        $year = now()->year;

        /* 001 and 002 drafted, then 001 deleted with its account: 002 is the only one left. */
        [, , $first] = $this->pendingApplication();
        [, , $second] = $this->pendingApplication();
        $one = app(DraftAgreement::class)->handle($first);
        app(DraftAgreement::class)->handle($second);
        $one->forceDelete();

        [, , $invitation] = $this->pendingApplication();
        $invitation->update(['source' => ApplicationSource::Invited]);

        app(RespondToInvitation::class)->accept($invitation->fresh());

        $this->assertSame("SDPC-{$year}-003", $invitation->refresh()->agreement->reference);
    }

    /**
     * A verified business with an open posting and one pending applicant.
     *
     * @param  array<string, mixed>  $projectAttributes
     * @return array{0: User, 1: Project, 2: Application}
     */
    private function pendingApplication(array $projectAttributes = []): array
    {
        $owner = User::factory()->verifiedBusiness()->create();

        $project = Project::factory()->create([
            'team_id' => $owner->current_team_id,
            'status' => ProjectStatus::Open,
            ...$projectAttributes,
        ]);

        $application = Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => User::factory()->student()->approved()->create()->id,
            'status' => ApplicationStatus::Pending,
        ]);

        return [$owner, $project, $application];
    }
}
