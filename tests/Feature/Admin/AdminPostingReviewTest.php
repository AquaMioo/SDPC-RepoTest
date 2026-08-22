<?php

namespace Tests\Feature\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\CredentialStatus;
use App\Enums\ProjectStatus;
use App\Models\Application;
use App\Models\Project;
use App\Models\User;
use App\Notifications\Client\ProjectStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Posting review, and the whole chain it unblocks.
 *
 * A client publishes into pending_review and the student board only lists open
 * postings, so without this queue nothing a client writes ever reaches anyone.
 */
class AdminPostingReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_sees_the_queue(): void
    {
        Project::factory()->create(['status' => ProjectStatus::PendingReview]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/dashboard')
                ->has('postings', 1)
                ->where('postings.0.awaitingDecision', true));
    }

    public function test_the_standalone_postings_screen_is_gone(): void
    {
        // The queue lives on the dashboard overview now. The decision endpoint
        // stayed put; only the screen was folded in.
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/postings')
            ->assertNotFound();
    }

    public function test_the_dashboard_also_carries_the_content_blocks(): void
    {
        // Content management folded into the same screen, so a page that
        // renders the queue without the copy would be half a merge.
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('content')
                ->has('postings'));
    }

    public function test_a_draft_is_not_in_the_queue(): void
    {
        Project::factory()->create(['status' => ProjectStatus::Draft]);

        // A draft has not been submitted, so there is nothing to decide.
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('postings', 0));
    }

    public function test_a_client_can_not_reach_the_queue(): void
    {
        $this->actingAs(User::factory()->client()->approved()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_a_student_can_not_reach_the_queue(): void
    {
        $this->actingAs(User::factory()->student()->approved()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_approving_opens_the_posting_and_tells_the_client(): void
    {
        Notification::fake();

        // The posting has to belong to a team with someone in it, or there is
        // nobody for the approval to notify.
        $client = User::factory()->client()->approved()->create();

        $project = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'status' => ProjectStatus::PendingReview,
            'published_at' => null,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.dashboard'))
            ->patch(route('admin.postings.update', ['posting' => $project]), [
                'status' => ProjectStatus::Open->value,
            ])
            ->assertSessionHasNoErrors();

        $project->refresh();

        $this->assertSame(ProjectStatus::Open, $project->status);
        $this->assertNotNull($project->published_at);

        Notification::assertSentTo(
            $project->team->members,
            ProjectStatusChanged::class,
        );
    }

    public function test_closing_takes_a_posting_back_off_the_board(): void
    {
        Notification::fake();

        $project = Project::factory()->create(['status' => ProjectStatus::Open]);

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.dashboard'))
            ->patch(route('admin.postings.update', ['posting' => $project]), [
                'status' => ProjectStatus::Closed->value,
            ]);

        $this->assertSame(ProjectStatus::Closed, $project->refresh()->status);
    }

    public function test_an_administrator_can_not_set_an_arbitrary_status(): void
    {
        $project = Project::factory()->create(['status' => ProjectStatus::PendingReview]);

        // Completing a project is the client's call, not a review decision.
        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.dashboard'))
            ->patch(route('admin.postings.update', ['posting' => $project]), [
                'status' => ProjectStatus::Completed->value,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(ProjectStatus::PendingReview, $project->refresh()->status);
    }

    public function test_the_whole_chain_from_posting_to_application_works(): void
    {
        Notification::fake();

        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
        $student = $this->verifiedStudent();
        $admin = User::factory()->admin()->create();

        // 1. The client publishes.
        $this->actingAs($client)
            ->post(route('projects.store', ['current_team' => $client->currentTeam]), [
                'title' => 'Inventory System',
                'description' => 'Replace the spreadsheet used across three branches.',
                'category' => 'Management / inventory system',
                'skills' => ['Laravel'],
                'status' => ProjectStatus::PendingReview->value,
            ])
            ->assertSessionHasNoErrors();

        $project = Project::firstOrFail();

        // 2. Before review, no student can see it.
        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('projects.data', 0));

        // 3. The administrator approves it.
        $this->actingAs($admin)
            ->from(route('admin.dashboard'))
            ->patch(route('admin.postings.update', ['posting' => $project]), [
                'status' => ProjectStatus::Open->value,
            ])
            ->assertSessionHasNoErrors();

        // 4. Now it is on the student's board.
        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('projects.data', 1)
                ->where('projects.data.0.title', 'Inventory System'));

        // 5. And the student can apply to it.
        $this->actingAs($student)
            ->from(route('student.board.show', [
                'current_team' => $student->currentTeam,
                'project' => $project,
            ]))
            ->post(route('student.board.apply', [
                'current_team' => $student->currentTeam,
                'project' => $project,
            ]), ['cover_letter' => 'I have built two inventory systems for shops nearby.'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('applications', [
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Pending->value,
        ]);

        // 6. And the client sees the applicant.
        $this->actingAs($client)
            ->get(route('projects.applicants.index', [
                'current_team' => $client->currentTeam,
                'project' => $project,
            ]))
            ->assertOk();

        $this->assertSame(1, Application::count());
    }

    /**
     * A student an administrator has cleared to apply for work.
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
}
