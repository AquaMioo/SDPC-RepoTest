<?php

namespace Tests\Feature\Admin;

use App\Enums\CredentialStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What "monitored" actually costs an account.
 *
 * It is a hold, not a ban. The account keeps signing in and keeps reading —
 * including the settings screen its appeal is written from — but stops
 * posting, applying, hiring and signing until an administrator decides.
 */
class MonitoredAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_monitored_account_can_still_sign_in(): void
    {
        $client = User::factory()->client()->create(['status' => UserStatus::Monitored]);

        $this->post(route('login.store'), [
            'email' => $client->email,
            'password' => 'password',
        ]);

        // Being reviewed must never lock somebody out of appealing.
        $this->assertAuthenticatedAs($client);
    }

    public function test_a_monitored_client_can_still_read_their_module(): void
    {
        $client = $this->monitoredClient();

        $this->actingAs($client)
            ->get(route('projects.index', ['current_team' => $client->currentTeam]))
            ->assertOk();

        $this->actingAs($client)
            ->get(route('recruit.index', ['current_team' => $client->currentTeam]))
            ->assertOk();
    }

    public function test_a_monitored_client_cannot_post_work(): void
    {
        $client = $this->monitoredClient();

        $this->actingAs($client)
            ->from(route('projects.index', ['current_team' => $client->currentTeam]))
            ->post(route('projects.store', ['current_team' => $client->currentTeam]), [
                'title' => 'Inventory System',
                'description' => 'Replace the spreadsheet used across three branches.',
                'category' => 'Management / inventory system',
                'skills' => ['Laravel'],
                'status' => ProjectStatus::PendingReview->value,
            ])
            ->assertSessionHasErrors('monitoring');

        $this->assertSame(0, Project::count());
    }

    public function test_a_monitored_client_cannot_reach_the_posting_form(): void
    {
        $client = $this->monitoredClient();

        $this->actingAs($client)
            ->from(route('projects.index', ['current_team' => $client->currentTeam]))
            ->get(route('projects.create', ['current_team' => $client->currentTeam]))
            ->assertSessionHasErrors('monitoring');
    }

    public function test_a_monitored_student_can_still_browse_the_board(): void
    {
        $student = $this->monitoredStudent();

        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertOk();
    }

    public function test_a_monitored_student_cannot_apply(): void
    {
        $student = $this->monitoredStudent();
        $project = Project::factory()->create(['status' => ProjectStatus::Open]);

        $this->actingAs($student)
            ->from(route('student.board.show', [
                'current_team' => $student->currentTeam,
                'project' => $project,
            ]))
            ->post(route('student.board.apply', [
                'current_team' => $student->currentTeam,
                'project' => $project,
            ]), ['cover_letter' => 'I have built two inventory systems for shops nearby.'])
            ->assertSessionHasErrors('monitoring');

        $this->assertDatabaseCount('applications', 0);
    }

    /**
     * The appeal is written from settings, so the account must be able to get
     * there. Gating this would close the only door out of monitoring.
     */
    public function test_a_monitored_account_can_still_reach_its_settings(): void
    {
        $client = $this->monitoredClient();

        $this->actingAs($client)->get(route('profile.edit'))->assertOk();
    }

    public function test_an_approved_account_is_unaffected(): void
    {
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();

        $this->actingAs($client)
            ->get(route('projects.create', ['current_team' => $client->currentTeam]))
            ->assertOk();
    }

    /**
     * A client whose permit was accepted and who was then put under review.
     *
     * Verified on purpose: otherwise EnsureAccountIsVerified would refuse the
     * write first and the monitoring gate would never be reached, so the test
     * would pass without proving anything.
     */
    private function monitoredClient(): User
    {
        return User::factory()
            ->client()
            ->verifiedBusiness()
            ->create(['status' => UserStatus::Monitored]);
    }

    /**
     * A student whose credential was accepted and who was then put under
     * review. Verified for the same reason the client above is.
     */
    private function monitoredStudent(): User
    {
        $student = User::factory()->student()->create(['status' => UserStatus::Monitored]);

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
