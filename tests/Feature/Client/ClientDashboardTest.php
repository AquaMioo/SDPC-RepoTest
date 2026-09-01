<?php

namespace Tests\Feature\Client;

use App\Enums\AgreementStatus;
use App\Enums\ApplicationStatus;
use App\Enums\MilestoneStatus;
use App\Enums\SiteContentKey;
use App\Enums\TeamRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\Application;
use App\Models\Project;
use App\Models\SiteContent;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_can_reach_their_workspace(): void
    {
        $user = User::factory()->client()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('client.dashboard', ['current_team' => $user->currentTeam]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('client/dashboard'));
    }

    public function test_a_client_lands_on_their_workspace_after_logging_in(): void
    {
        $user = User::factory()->client()->create();

        $response = $this
            ->followingRedirects()
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticated();
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('client/dashboard'));
    }

    public function test_a_student_cannot_reach_the_client_workspace(): void
    {
        $user = User::factory()->student()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('client.dashboard', ['current_team' => $user->currentTeam]));

        $response->assertForbidden();
    }

    public function test_the_workspace_carries_pending_invitations(): void
    {
        $owner = User::factory()->create(['name' => 'Taylor Otwell']);
        $client = User::factory()->client()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create(['name' => 'Laravel Team']);

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($client)
            ->get(route('client.dashboard', ['current_team' => $client->currentTeam]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('client/dashboard')
            ->has('pendingInvitations', 1)
            ->where('pendingInvitations.0.code', $invitation->code)
            ->where('pendingInvitations.0.inviterName', 'Taylor Otwell')
            ->where('pendingInvitations.0.team.name', 'Laravel Team'),
        );
    }

    public function test_the_workspace_ignores_invitations_addressed_to_someone_else(): void
    {
        $owner = User::factory()->create();
        $client = User::factory()->client()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'someone@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($client)
            ->get(route('client.dashboard', ['current_team' => $client->currentTeam]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('client/dashboard')
            ->has('pendingInvitations', 0),
        );
    }

    /**
     * The screen greets the person, not the business.
     */
    public function test_the_overview_greets_the_viewer_by_name(): void
    {
        $client = User::factory()->client()->create(['name' => 'Samuel Clemens']);

        $this->actingAs($client)
            ->get(route('client.dashboard', ['current_team' => $client->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('client/dashboard')
                ->where('userName', 'Samuel Clemens')
                ->etc()
            );
    }

    /**
     * The counts, activity feed and shortlist were taken off this screen. A
     * prop quietly reappearing would put the old layout back.
     */
    public function test_the_overview_no_longer_carries_the_old_panels(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get(route('client.dashboard', ['current_team' => $client->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('client/dashboard')
                ->missing('stats')
                ->missing('profileCompletion')
                ->missing('recentActivity')
                ->missing('shortlistedStudents')
                ->etc()
            );
    }

    /**
     * What an administrator saves on the Content screen is what a client
     * reads here — the same row the student dashboard shows.
     */
    public function test_the_overview_shows_the_announcement_an_administrator_saved(): void
    {
        $client = User::factory()->client()->create();

        SiteContent::create([
            'key' => SiteContentKey::Announcements,
            'body' => "Enrolment closes on the 30th.\nSubmit your permits before then.",
        ]);

        $this->actingAs($client)
            ->get(route('client.dashboard', ['current_team' => $client->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('client/dashboard')
                ->where('announcement.body', "Enrolment closes on the 30th.\nSubmit your permits before then.")
                ->etc()
            );
    }

    public function test_the_announcement_is_null_when_nothing_has_been_saved(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get(route('client.dashboard', ['current_team' => $client->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('client/dashboard')
                ->where('announcement', null)
                ->etc()
            );
    }

    /**
     * A row saved and then cleared should read as nothing posted, not as an
     * empty panel with an "updated" timestamp on it.
     */
    public function test_a_blank_announcement_body_reads_as_nothing_posted(): void
    {
        $client = User::factory()->client()->create();

        SiteContent::create([
            'key' => SiteContentKey::Announcements,
            'body' => '   ',
        ]);

        $this->actingAs($client)
            ->get(route('client.dashboard', ['current_team' => $client->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('client/dashboard')
                ->where('announcement', null)
                ->etc()
            );
    }

    public function test_the_overview_reports_milestone_progress_from_the_agreement(): void
    {
        [$client, $team, $project] = $this->teamWithProject();

        $agreement = Agreement::factory()->create([
            'team_id' => $team->id,
            'project_id' => $project->id,
            'status' => AgreementStatus::Active,
        ]);

        // Two of four approved: the ring reads the milestones, not the posting.
        foreach ([MilestoneStatus::Approved, MilestoneStatus::Approved, MilestoneStatus::Pending, MilestoneStatus::Pending] as $position => $status) {
            AgreementMilestone::factory()->create([
                'agreement_id' => $agreement->id,
                'position' => $position + 1,
                'status' => $status,
            ]);
        }

        $this->partialDashboard($client, $team, 'currentProject')
            ->assertJsonPath('props.currentProject.progress', 50)
            ->assertJsonCount(4, 'props.currentProject.milestones');
    }

    public function test_the_overview_has_no_current_project_without_an_agreement(): void
    {
        [$client, $team] = $this->teamWithProject();

        $this->partialDashboard($client, $team, 'currentProject')
            ->assertJsonPath('props.currentProject', null);
    }

    public function test_the_overview_lists_the_accepted_students_as_the_project_team(): void
    {
        [$client, $team, $project] = $this->teamWithProject();

        Application::factory()->count(2)->create([
            'project_id' => $project->id,
            'status' => ApplicationStatus::Accepted,
        ]);
        Application::factory()->create([
            'project_id' => $project->id,
            'status' => ApplicationStatus::Pending,
        ]);

        $this->partialDashboard($client, $team, 'projectTeam')
            ->assertJsonCount(2, 'props.projectTeam');
    }

    public function test_the_team_panel_says_who_is_here_not_who_is_on_it(): void
    {
        [$client, $team, $project] = $this->teamWithProject();

        $here = User::factory()->student()->approved()->create();
        $gone = User::factory()->student()->approved()->create();

        $here->forceFill(['last_seen_at' => now()])->saveQuietly();
        /* Signed out: the listener nulls the stamp rather than ageing it. */
        $gone->forceFill(['last_seen_at' => null])->saveQuietly();

        foreach ([$here, $gone] as $student) {
            Application::factory()->create([
                'project_id' => $project->id,
                'user_id' => $student->id,
                'status' => ApplicationStatus::Accepted,
            ]);
        }

        $response = $this->partialDashboard($client, $team, 'projectTeam');

        $members = collect($response->json('props.projectTeam'))
            ->keyBy('id');

        $this->assertTrue($members[$here->id]['isOnline']);
        $this->assertFalse($members[$gone->id]['isOnline']);
    }

    public function test_a_student_who_wandered_off_stops_reading_as_here(): void
    {
        [$client, $team, $project] = $this->teamWithProject();

        $student = User::factory()->student()->approved()->create();
        $student->forceFill([
            'last_seen_at' => now()->subMinutes(User::PRESENCE_WINDOW_MINUTES + 1),
        ])->saveQuietly();

        Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Accepted,
        ]);

        $this->partialDashboard($client, $team, 'projectTeam')
            ->assertJsonPath('props.projectTeam.0.isOnline', false);
    }

    public function test_the_calendar_carries_only_dated_milestones(): void
    {
        [$client, $team, $project] = $this->teamWithProject();

        $agreement = Agreement::factory()->create([
            'team_id' => $team->id,
            'project_id' => $project->id,
            'status' => AgreementStatus::Active,
        ]);

        AgreementMilestone::factory()->create([
            'agreement_id' => $agreement->id,
            'position' => 1,
            'ends_on' => now()->addWeek()->toDateString(),
        ]);
        AgreementMilestone::factory()->create([
            'agreement_id' => $agreement->id,
            'position' => 2,
            'ends_on' => null,
        ]);

        $this->partialDashboard($client, $team, 'calendarEvents')
            ->assertJsonCount(1, 'props.calendarEvents');
    }

    /**
     * Fetch the dashboard asking for one deferred prop.
     *
     * The three overview panels are Inertia::defer, so they are absent from
     * the first response by design; a partial visit is what resolves them.
     */
    private function partialDashboard(User $client, Team $team, string $prop)
    {
        return $this->actingAs($client)
            ->get(
                route('client.dashboard', ['current_team' => $team->slug]),
                [
                    'X-Inertia' => 'true',
                    /*
                     * The asset version the middleware will compare against.
                     * Anything else — including omitting it — 409s, because a
                     * version mismatch is how Inertia forces a hard refresh.
                     */
                    'X-Inertia-Version' => app(HandleInertiaRequests::class)
                        ->version(request()),
                    'X-Inertia-Partial-Component' => 'client/dashboard',
                    'X-Inertia-Partial-Data' => $prop,
                ],
            )
            ->assertOk();
    }

    /**
     * A client owning a team that has one posting.
     *
     * @return array{0: User, 1: Team, 2: Project}
     */
    private function teamWithProject(): array
    {
        $client = User::factory()->client()->approved()->create();
        $team = $client->currentTeam;

        $project = Project::factory()->create([
            'team_id' => $team->id,
            'created_by' => $client->id,
        ]);

        // ProjectFactory's incidental users overwrite the URL team default.
        $client->switchTeam($team);

        return [$client->fresh(), $team, $project];
    }
}
