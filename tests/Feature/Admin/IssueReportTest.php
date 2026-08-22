<?php

namespace Tests\Feature\Admin;

use App\Enums\IssueCategory;
use App\Enums\IssueStatus;
use App\Enums\UserStatus;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reporting an account, and what an administrator can do about it.
 *
 * A report is a statement, not a verdict: filing one changes nothing about the
 * reported account until an administrator resolves it.
 */
class IssueReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_can_report_an_account(): void
    {
        $client = User::factory()->client()->approved()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($client)
            ->from('/')
            ->post(route('reports.store'), [
                'reported_user_id' => $student->id,
                'category' => IssueCategory::Misrepresentation->value,
                'description' => 'The listed skills do not match the work delivered on our posting.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('issues', [
            'reporter_id' => $client->id,
            'reported_user_id' => $student->id,
            'category' => IssueCategory::Misrepresentation->value,
            'status' => IssueStatus::Pending->value,
        ]);
    }

    public function test_a_student_can_report_an_account(): void
    {
        $student = User::factory()->student()->create();
        $client = User::factory()->client()->create();

        $this->actingAs($student)
            ->from('/')
            ->post(route('reports.store'), [
                'reported_user_id' => $client->id,
                'category' => IssueCategory::DelayedDelivery->value,
                'description' => 'The business stopped responding halfway through the agreed milestone.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('issues', 1);
    }

    /**
     * The gate keeps unproven accounts read only, but being unable to report
     * the account harassing you is not a sensible read-only.
     */
    public function test_an_unverified_account_may_still_report(): void
    {
        $unverified = User::factory()->client()->create();
        $other = User::factory()->student()->create();

        $this->assertFalse($unverified->isVerifiedForOperating());

        $this->actingAs($unverified)
            ->from('/')
            ->post(route('reports.store'), [
                'reported_user_id' => $other->id,
                'category' => IssueCategory::Harassment->value,
                'description' => 'Repeated abusive messages after I declined the invitation.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('issues', 1);
    }

    public function test_an_account_cannot_report_itself(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/')
            ->post(route('reports.store'), [
                'reported_user_id' => $user->id,
                'category' => IssueCategory::Other->value,
                'description' => 'Trying to report my own account for some reason.',
            ])
            ->assertSessionHasErrors('reported_user_id');

        $this->assertDatabaseCount('issues', 0);
    }

    public function test_a_too_short_description_is_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->from('/')
            ->post(route('reports.store'), [
                'reported_user_id' => $other->id,
                'category' => IssueCategory::Other->value,
                'description' => 'bad',
            ])
            ->assertSessionHasErrors('description');

        $this->assertDatabaseCount('issues', 0);
    }

    public function test_a_second_report_about_the_same_account_is_not_queued_twice(): void
    {
        $reporter = User::factory()->create();
        $reported = User::factory()->create();

        $payload = [
            'reported_user_id' => $reported->id,
            'category' => IssueCategory::Other->value,
            'description' => 'The same complaint filed twice in a row by the same account.',
        ];

        $this->actingAs($reporter)->from('/')->post(route('reports.store'), $payload);
        $this->actingAs($reporter)->from('/')->post(route('reports.store'), $payload);

        $this->assertDatabaseCount('issues', 1);
    }

    /**
     * Once the first is decided the pair is free again — the same account can
     * misbehave twice.
     */
    public function test_a_new_report_is_allowed_once_the_previous_one_is_resolved(): void
    {
        $reporter = User::factory()->create();
        $reported = User::factory()->create();

        Issue::factory()->resolved()->create([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reported->id,
        ]);

        $this->actingAs($reporter)
            ->from('/')
            ->post(route('reports.store'), [
                'reported_user_id' => $reported->id,
                'category' => IssueCategory::Harassment->value,
                'description' => 'It started again a week after the first report was closed.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('issues', 2);
    }

    public function test_a_guest_cannot_report(): void
    {
        $reported = User::factory()->create();

        $this->post(route('reports.store'), [
            'reported_user_id' => $reported->id,
            'category' => IssueCategory::Other->value,
            'description' => 'A report filed by nobody at all, which should not land.',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('issues', 0);
    }

    public function test_the_admin_queue_lists_real_reports(): void
    {
        $admin = User::factory()->admin()->create();
        Issue::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('admin.issues'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/issues')
                ->has('issues', 3)
            );
    }

    public function test_warning_closes_the_report_without_touching_the_account(): void
    {
        $admin = User::factory()->admin()->create();
        $reported = User::factory()->approved()->create();
        $issue = Issue::factory()->create(['reported_user_id' => $reported->id]);

        $this->actingAs($admin)
            ->from(route('admin.issues'))
            ->patch(route('admin.issues.update', $issue), ['action' => 'warn'])
            ->assertSessionHasNoErrors();

        $issue->refresh();

        $this->assertSame(IssueStatus::Resolved, $issue->status);
        $this->assertSame('Warned', $issue->resolution);
        $this->assertSame($admin->id, $issue->handled_by);
        $this->assertNotNull($issue->handled_at);

        // A warning is a warning; the account keeps its access.
        $this->assertSame(UserStatus::Approved, $reported->fresh()->status);
    }

    public function test_removing_access_deactivates_the_reported_account(): void
    {
        $admin = User::factory()->admin()->create();
        $reported = User::factory()->approved()->create();
        $issue = Issue::factory()->create(['reported_user_id' => $reported->id]);

        $this->actingAs($admin)
            ->from(route('admin.issues'))
            ->patch(route('admin.issues.update', $issue), ['action' => 'remove_access'])
            ->assertSessionHasNoErrors();

        $issue->refresh();

        $this->assertSame(IssueStatus::Resolved, $issue->status);
        $this->assertSame('Access removed', $issue->resolution);
        $this->assertSame(UserStatus::Deactivated, $reported->fresh()->status);
    }

    public function test_an_unknown_action_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $issue = Issue::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.issues'))
            ->patch(route('admin.issues.update', $issue), ['action' => 'delete_everything'])
            ->assertSessionHasErrors('action');

        $this->assertSame(IssueStatus::Pending, $issue->fresh()->status);
    }

    public function test_a_non_admin_cannot_reach_the_queue_or_resolve_a_report(): void
    {
        $client = User::factory()->client()->approved()->create();
        $issue = Issue::factory()->create();

        $this->actingAs($client)->get(route('admin.issues'))->assertForbidden();

        $this->actingAs($client)
            ->patch(route('admin.issues.update', $issue), ['action' => 'remove_access'])
            ->assertForbidden();

        $this->assertSame(IssueStatus::Pending, $issue->fresh()->status);
    }

    public function test_deleting_the_reported_account_removes_the_report(): void
    {
        $reported = User::factory()->create();
        $issue = Issue::factory()->create(['reported_user_id' => $reported->id]);

        $reported->forceDelete();

        $this->assertDatabaseMissing('issues', ['id' => $issue->id]);
    }
}
