<?php

namespace Tests\Feature\Admin;

use App\Enums\IssueCategory;
use App\Enums\IssueStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserStatus;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Reporting a posting, and what an administrator can do about it.
 *
 * A posting report still names an account. Closing the listing alone leaves
 * whoever wrote it free to write another, so the queue always has somebody to
 * act on — and the two decisions stay separate.
 */
class PostingReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_can_report_a_posting(): void
    {
        $student = User::factory()->student()->create();
        $posting = $this->posting();

        $this->actingAs($student)
            ->from('/')
            ->post(route('reports.store'), [
                'reported_project_id' => $posting->id,
                'category' => IssueCategory::MisleadingPosting->value,
                'description' => 'The listing describes paid work but the brief asks for it unpaid.',
            ])
            ->assertSessionHasNoErrors();

        // The author is who the report lands against, resolved server side.
        $this->assertDatabaseHas('issues', [
            'reporter_id' => $student->id,
            'reported_user_id' => $posting->created_by,
            'reported_project_id' => $posting->id,
            'category' => IssueCategory::MisleadingPosting->value,
            'status' => IssueStatus::Pending->value,
        ]);
    }

    /**
     * The browser names only the posting. Whoever answers for it is looked up
     * here, so a crafted request cannot pin one team's listing on another.
     */
    public function test_the_reported_account_is_not_taken_from_the_request(): void
    {
        $student = User::factory()->student()->create();
        $innocent = User::factory()->client()->create();
        $posting = $this->posting();

        $this->actingAs($student)
            ->from('/')
            ->post(route('reports.store'), [
                'reported_project_id' => $posting->id,
                'reported_user_id' => $innocent->id,
                'category' => IssueCategory::Spam->value,
                'description' => 'The same listing has been posted four times this week.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('issues', ['reported_user_id' => $posting->created_by]);
        $this->assertDatabaseMissing('issues', ['reported_user_id' => $innocent->id]);
    }

    public function test_a_client_cannot_report_their_own_teams_posting(): void
    {
        $client = User::factory()->client()->approved()->create();

        $posting = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'created_by' => $client->id,
        ]);

        $this->actingAs($client)
            ->from('/')
            ->post(route('reports.store'), [
                'reported_project_id' => $posting->id,
                'category' => IssueCategory::Spam->value,
                'description' => 'Reporting the listing my own team published, for some reason.',
            ])
            ->assertSessionHasErrors('reported_project_id');

        $this->assertDatabaseCount('issues', 0);
    }

    public function test_a_category_that_describes_a_person_is_rejected_on_a_posting(): void
    {
        $student = User::factory()->student()->create();
        $posting = $this->posting();

        // "Harassment" is a complaint about conduct, not about a listing.
        $this->actingAs($student)
            ->from('/')
            ->post(route('reports.store'), [
                'reported_project_id' => $posting->id,
                'category' => IssueCategory::Harassment->value,
                'description' => 'Using an account reason against a posting, which does not fit.',
            ])
            ->assertSessionHasErrors('category');

        $this->assertDatabaseCount('issues', 0);
    }

    /**
     * Reporting the account and reporting one of its postings are different
     * complaints, so neither buries the other.
     */
    public function test_a_posting_report_does_not_block_a_report_about_the_account(): void
    {
        $student = User::factory()->student()->create();
        $posting = $this->posting();

        $this->actingAs($student)->from('/')->post(route('reports.store'), [
            'reported_project_id' => $posting->id,
            'category' => IssueCategory::MisleadingPosting->value,
            'description' => 'The listing promises a budget that the brief then withdraws.',
        ]);

        $this->actingAs($student)->from('/')->post(route('reports.store'), [
            'reported_user_id' => $posting->created_by,
            'category' => IssueCategory::Harassment->value,
            'description' => 'And they became abusive when I asked about the budget.',
        ]);

        $this->assertDatabaseCount('issues', 2);
    }

    public function test_the_same_posting_is_not_queued_twice(): void
    {
        $student = User::factory()->student()->create();
        $posting = $this->posting();

        $payload = [
            'reported_project_id' => $posting->id,
            'category' => IssueCategory::Spam->value,
            'description' => 'The same complaint filed twice in a row by the same account.',
        ];

        $this->actingAs($student)->from('/')->post(route('reports.store'), $payload);
        $this->actingAs($student)->from('/')->post(route('reports.store'), $payload);

        $this->assertDatabaseCount('issues', 1);
    }

    public function test_the_admin_queue_shows_which_posting_a_report_is_about(): void
    {
        $admin = User::factory()->admin()->create();
        $posting = $this->posting(['title' => 'Inventory System']);

        Issue::factory()->aboutPosting($posting)->create();

        $this->actingAs($admin)
            ->get(route('admin.issues'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('issues.0.reportedPosting.title', 'Inventory System')
                ->where('issues.0.reportedPosting.closed', false)
                // Closing the posting is only offered when there is one.
                ->where('issues.0.actions.3.value', 'close_posting'),
            );
    }

    public function test_an_account_report_is_not_offered_the_posting_action(): void
    {
        $admin = User::factory()->admin()->create();

        Issue::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.issues'))
            ->assertInertia(fn (Assert $page) => $page->has('issues.0.actions', 3));
    }

    public function test_closing_a_posting_takes_it_off_the_board_and_leaves_the_account_alone(): void
    {
        $admin = User::factory()->admin()->create();
        $posting = $this->posting();
        $author = User::find($posting->created_by);
        $author->forceFill(['status' => UserStatus::Approved])->save();

        $issue = Issue::factory()->aboutPosting($posting)->create([
            'reported_user_id' => $author->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.issues'))
            ->patch(route('admin.issues.update', $issue), ['action' => 'close_posting'])
            ->assertSessionHasNoErrors();

        $issue->refresh();

        $this->assertSame(IssueStatus::Resolved, $issue->status);
        $this->assertSame('Posting closed', $issue->resolution);
        $this->assertSame(ProjectStatus::Closed, $posting->refresh()->status);

        // Taking a listing down is not a verdict on its author.
        $this->assertSame(UserStatus::Approved, $author->fresh()->status);
    }

    public function test_closing_a_posting_is_refused_when_the_report_names_none(): void
    {
        $admin = User::factory()->admin()->create();
        $issue = Issue::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.issues'))
            ->patch(route('admin.issues.update', $issue), ['action' => 'close_posting'])
            ->assertSessionHasErrors('action');

        $this->assertSame(IssueStatus::Pending, $issue->fresh()->status);
    }

    public function test_monitoring_restricts_the_reported_account_without_locking_it_out(): void
    {
        $admin = User::factory()->admin()->create();
        $reported = User::factory()->approved()->create();
        $issue = Issue::factory()->create(['reported_user_id' => $reported->id]);

        $this->actingAs($admin)
            ->from(route('admin.issues'))
            ->patch(route('admin.issues.update', $issue), ['action' => 'monitor'])
            ->assertSessionHasNoErrors();

        $reported->refresh();

        $this->assertSame(UserStatus::Monitored, $reported->status);
        $this->assertSame('Placed under monitoring', $issue->fresh()->resolution);

        // Monitoring is not deactivation: they must still be able to appeal.
        $this->assertTrue($reported->status->canAuthenticate());
    }

    public function test_deleting_the_posting_removes_the_report(): void
    {
        $posting = $this->posting();
        $issue = Issue::factory()->aboutPosting($posting)->create();

        $posting->forceDelete();

        $this->assertDatabaseMissing('issues', ['id' => $issue->id]);
    }

    /**
     * A posting written by somebody other than the person reporting it.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function posting(array $attributes = []): Project
    {
        $client = User::factory()->client()->approved()->create();

        return Project::factory()->create([
            'team_id' => $client->current_team_id,
            'created_by' => $client->id,
            ...$attributes,
        ]);
    }
}
