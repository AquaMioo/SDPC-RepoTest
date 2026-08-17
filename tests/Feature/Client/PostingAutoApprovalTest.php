<?php

namespace Tests\Feature\Client;

use App\Enums\CredentialStatus;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * projects.auto_approve is a local demo convenience: it lets one person walk
 * the client-to-student path without switching to the admin portal in between.
 *
 * It must stay off by default, and it must not become a way to move a posting
 * anywhere other than out of the review queue.
 */
class PostingAutoApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_suite_runs_against_the_reviewed_workflow(): void
    {
        // Pinned in phpunit.xml. Without it, a developer with the flag on in
        // their own .env would silently stop testing the real path.
        $this->assertFalse(config('projects.auto_approve'));
    }

    public function test_the_shipped_default_is_off(): void
    {
        // The guard that actually protects a deployment: a fresh clone that
        // never sets the variable must still send postings through review.
        $this->assertStringContainsString(
            'PROJECTS_AUTO_APPROVE=false',
            (string) file_get_contents(base_path('.env.example')),
        );
    }

    public function test_without_the_flag_a_posting_waits_for_review(): void
    {
        config(['projects.auto_approve' => false]);

        $client = User::factory()->client()->approved()->verifiedBusiness()->create();

        $this->actingAs($client)
            ->post(
                route('projects.store', ['current_team' => $client->currentTeam]),
                $this->payload(),
            )
            ->assertSessionHasNoErrors();

        $this->assertSame(ProjectStatus::PendingReview, Project::firstOrFail()->status);
    }

    public function test_with_the_flag_a_posting_opens_immediately(): void
    {
        config(['projects.auto_approve' => true]);

        $client = User::factory()->client()->approved()->verifiedBusiness()->create();

        $this->actingAs($client)
            ->post(
                route('projects.store', ['current_team' => $client->currentTeam]),
                $this->payload(),
            )
            ->assertSessionHasNoErrors();

        $project = Project::firstOrFail();

        $this->assertSame(ProjectStatus::Open, $project->status);
        $this->assertNotNull($project->published_at);
    }

    public function test_the_flag_never_promotes_a_draft(): void
    {
        config(['projects.auto_approve' => true]);

        $client = User::factory()->client()->approved()->verifiedBusiness()->create();

        // Saving a draft is "not finished yet", not "publish this for me".
        $this->actingAs($client)
            ->post(
                route('projects.store', ['current_team' => $client->currentTeam]),
                $this->payload(['status' => ProjectStatus::Draft->value]),
            )
            ->assertSessionHasNoErrors();

        $project = Project::firstOrFail();

        $this->assertSame(ProjectStatus::Draft, $project->status);
        $this->assertNull($project->published_at);
    }

    public function test_a_posted_project_reaches_the_student_board_with_the_flag_on(): void
    {
        config(['projects.auto_approve' => true]);

        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
        $student = $this->verifiedStudent();

        $this->actingAs($client)
            ->post(
                route('projects.store', ['current_team' => $client->currentTeam]),
                $this->payload(['title' => 'Inventory System']),
            )
            ->assertSessionHasNoErrors();

        // No admin step in between — that is the whole point of the flag.
        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('projects.data', 1)
                ->where('projects.data.0.title', 'Inventory System'));
    }

    /**
     * A student cleared to apply for work.
     */
    private function verifiedStudent(): User
    {
        $student = User::factory()->student()->approved()->create();

        $student->studentCredentials()->create([
            'school' => 'City College of Technology',
            'disk' => 'local',
            'path' => 'credentials/'.$student->id.'.pdf',
            'original_name' => 'registration.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'checksum' => hash('sha256', (string) $student->id),
        ])->forceFill(['status' => CredentialStatus::Verified])->save();

        return $student->fresh();
    }

    /**
     * A posting that satisfies SaveProjectRequest.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Inventory System',
            'description' => 'Replace the spreadsheet used across three branches.',
            'category' => 'Management / inventory system',
            'skills' => ['Laravel'],
            'status' => ProjectStatus::PendingReview->value,
            ...$overrides,
        ];
    }
}
