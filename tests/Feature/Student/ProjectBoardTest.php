<?php

namespace Tests\Feature\Student;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\CredentialStatus;
use App\Enums\ProjectStatus;
use App\Models\Application;
use App\Models\Project;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * "Get Client" — the board a student browses for work, and the application
 * they send from it.
 */
class ProjectBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_can_open_the_board(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('student/find-clients'));
    }

    public function test_a_client_is_kept_out_of_the_student_module(): void
    {
        $client = User::factory()->client()->approved()->create();

        $this->actingAs($client)
            ->get(route('student.board.index', ['current_team' => $client->currentTeam]))
            ->assertForbidden();
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $student = $this->student();

        $this->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertRedirect(route('login'));
    }

    public function test_open_postings_reach_the_board(): void
    {
        $student = $this->student();
        $this->posting(['title' => 'Inventory System']);

        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('projects.data', 1)
                ->where('projects.data.0.title', 'Inventory System'));
    }

    public function test_a_draft_never_reaches_the_board(): void
    {
        $student = $this->student();
        $this->posting(['status' => ProjectStatus::Draft]);

        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('projects.data', 0));
    }

    public function test_the_board_can_be_searched(): void
    {
        $student = $this->student();
        $this->posting(['title' => 'Inventory System']);
        $this->posting(['title' => 'Delivery Tracker']);

        $this->actingAs($student)
            ->get(route('student.board.index', [
                'current_team' => $student->currentTeam,
                'search' => 'Delivery',
            ]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('projects.data', 1)
                ->where('projects.data.0.title', 'Delivery Tracker'));
    }

    public function test_a_verified_student_can_apply(): void
    {
        Notification::fake();

        $student = $this->verifiedStudent();
        $project = $this->posting();

        $this->actingAs($student)
            ->from(route('student.board.show', [
                'current_team' => $student->currentTeam,
                'project' => $project,
            ]))
            ->post(route('student.board.apply', [
                'current_team' => $student->currentTeam,
                'project' => $project,
            ]), [
                'cover_letter' => 'I have built two inventory systems for shops in San Jose Del Monte.',
                'proposed_rate' => 250,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('applications', [
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Pending->value,
            'source' => ApplicationSource::Applied->value,
            'proposed_rate' => 250,
        ]);
    }

    /**
     * The gate is the verification provider's answer, so it only closes when a
     * provider is configured — with none there is nothing to check against.
     */
    public function test_an_unverified_student_can_browse_but_not_apply(): void
    {
        config([
            'sheerid.enabled' => true,
            'sheerid.program_id' => 'prog_test',
            'sheerid.access_token' => 'token_test',
        ]);

        $student = $this->student();
        $project = $this->posting();

        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canApply', false));

        $this->actingAs($student)
            ->from(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->post(route('student.board.apply', [
                'current_team' => $student->currentTeam,
                'project' => $project,
            ]), ['cover_letter' => str_repeat('Please let me build this for you. ', 3)])
            ->assertSessionHasErrors('verification');

        $this->assertSame(0, Application::count());
    }

    public function test_a_student_can_not_apply_twice(): void
    {
        Notification::fake();

        $student = $this->verifiedStudent();
        $project = $this->posting();

        $payload = ['cover_letter' => str_repeat('I would like to build this system. ', 3)];
        $url = route('student.board.apply', [
            'current_team' => $student->currentTeam,
            'project' => $project,
        ]);
        $from = route('student.board.show', [
            'current_team' => $student->currentTeam,
            'project' => $project,
        ]);

        $this->actingAs($student)->from($from)->post($url, $payload)->assertSessionHasNoErrors();
        $this->actingAs($student)->from($from)->post($url, $payload)->assertSessionHasErrors('application');

        $this->assertSame(1, Application::count());
    }

    public function test_a_cover_letter_has_to_say_something(): void
    {
        $student = $this->verifiedStudent();
        $project = $this->posting();

        $this->actingAs($student)
            ->from(route('student.board.show', [
                'current_team' => $student->currentTeam,
                'project' => $project,
            ]))
            ->post(route('student.board.apply', [
                'current_team' => $student->currentTeam,
                'project' => $project,
            ]), ['cover_letter' => 'Hire me.'])
            ->assertSessionHasErrors('cover_letter');

        $this->assertSame(0, Application::count());
    }

    public function test_a_closed_posting_can_not_be_applied_to(): void
    {
        $student = $this->verifiedStudent();
        $project = $this->posting(['applications_open' => false]);

        $this->actingAs($student)
            ->from(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->post(route('student.board.apply', [
                'current_team' => $student->currentTeam,
                'project' => $project,
            ]), ['cover_letter' => str_repeat('I would like to build this system. ', 3)])
            ->assertSessionHasErrors('application');

        $this->assertSame(0, Application::count());
    }

    public function test_a_student_can_withdraw_an_undecided_application(): void
    {
        $student = $this->verifiedStudent();
        $application = Application::factory()->create([
            'project_id' => $this->posting()->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Pending,
        ]);

        $this->actingAs($student)
            ->from(route('student.workflow', ['current_team' => $student->currentTeam]))
            ->delete(route('student.applications.withdraw', [
                'current_team' => $student->currentTeam,
                'application' => $application,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::Withdrawn, $application->fresh()->status);
    }

    public function test_a_decided_application_can_not_be_withdrawn(): void
    {
        $student = $this->verifiedStudent();
        $application = Application::factory()->create([
            'project_id' => $this->posting()->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Accepted,
        ]);

        $this->actingAs($student)
            ->from(route('student.workflow', ['current_team' => $student->currentTeam]))
            ->delete(route('student.applications.withdraw', [
                'current_team' => $student->currentTeam,
                'application' => $application,
            ]))
            ->assertSessionHasErrors('application');

        $this->assertSame(ApplicationStatus::Accepted, $application->fresh()->status);
    }

    public function test_a_student_can_not_withdraw_someone_elses_application(): void
    {
        $student = $this->verifiedStudent();
        $stranger = $this->verifiedStudent();

        $application = Application::factory()->create([
            'project_id' => $this->posting()->id,
            'user_id' => $stranger->id,
            'status' => ApplicationStatus::Pending,
        ]);

        $this->actingAs($student)
            ->delete(route('student.applications.withdraw', [
                'current_team' => $student->currentTeam,
                'application' => $application,
            ]))
            ->assertForbidden();

        $this->assertSame(ApplicationStatus::Pending, $application->fresh()->status);
    }

    public function test_the_match_panel_stays_off_when_nothing_scored(): void
    {
        // The stored driver reads a table nothing has written to. A zeroed
        // ring would read as a failed match rather than an absent one.
        config(['recommendations.driver' => 'stored']);

        $student = $this->student();
        $this->posting();

        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('matchingEnabled', false)
                ->where('highlight', null)
                ->where('projects.data.0.compatibility', null));
    }

    public function test_a_scored_brief_carries_its_match_and_insight(): void
    {
        config(['recommendations.driver' => 'stored']);

        $student = $this->student();
        $project = $this->posting(['title' => 'HandyGo']);

        Recommendation::create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'score' => 0.92,
            'compatibility_percentage' => 92,
            'reason' => [
                'insight' => 'Perfect alignment with your geolocation work.',
                'recommendation' => 'Lead your pitch with the booking flow.',
                'factors' => [
                    ['label' => 'React & Next.js', 'value' => 95],
                    ['label' => 'Multi-tenant data', 'value' => 76],
                    ['label' => 'not a factor'],
                ],
            ],
        ]);

        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('matchingEnabled', true)
                ->where('projects.data.0.compatibility', 92)
                ->where('projects.data.0.insight', 'Perfect alignment with your geolocation work.')
                ->where('highlight.title', 'HandyGo')
                ->where('highlight.compatibility', 92)
                ->where('highlight.recommendation', 'Lead your pitch with the booking flow.')
                // The malformed third factor is dropped, not guessed at.
                ->has('highlight.factors', 2)
                ->where('highlight.factors.0.label', 'React & Next.js'));
    }

    public function test_another_students_score_is_not_borrowed(): void
    {
        config(['recommendations.driver' => 'stored']);

        $student = $this->student();
        $stranger = $this->student();
        $project = $this->posting();

        Recommendation::create([
            'project_id' => $project->id,
            'user_id' => $stranger->id,
            'score' => 0.99,
            'compatibility_percentage' => 99,
        ]);

        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('projects.data.0.compatibility', null)
                ->where('highlight', null));
    }

    public function test_an_unknown_sort_falls_back_rather_than_erroring(): void
    {
        $student = $this->student();
        $this->posting();

        $this->actingAs($student)
            ->get(route('student.board.index', [
                'current_team' => $student->currentTeam,
                'sort' => 'drop table',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.sort', 'recommended'));
    }

    /*
     * ---------------------------------------------------------------------
     * The order the board comes back in
     * ---------------------------------------------------------------------
     *
     * The sort was read off the query string and handed to the screen, but
     * never applied — "Recommended" and "Newest" returned the same list, so
     * the tab did nothing.
     */

    public function test_newest_orders_by_when_the_posting_was_published(): void
    {
        $student = $this->student();

        $this->posting(['title' => 'Older', 'published_at' => now()->subWeek()]);
        $this->posting(['title' => 'Newer', 'published_at' => now()]);

        $this->actingAs($student)
            ->get(route('student.board.index', [
                'current_team' => $student->currentTeam,
                'sort' => 'newest',
            ]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('projects.data.0.title', 'Newer')
                ->where('projects.data.1.title', 'Older'));
    }

    public function test_recommended_puts_the_best_fitting_posting_first(): void
    {
        config(['recommendations.driver' => 'stored']);

        $student = $this->student();

        // The weaker fit is also the newer posting, so date order alone would
        // put it first — which is exactly what the broken sort used to do.
        $strong = $this->posting(['title' => 'Strong fit', 'published_at' => now()->subWeek()]);
        $this->posting(['title' => 'Weak fit', 'published_at' => now()]);

        Recommendation::create([
            'project_id' => $strong->id,
            'user_id' => $student->id,
            'score' => 0.91,
            'compatibility_percentage' => 91,
        ]);

        $this->actingAs($student)
            ->get(route('student.board.index', [
                'current_team' => $student->currentTeam,
                'sort' => 'recommended',
            ]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('projects.data.0.title', 'Strong fit')
                ->where('projects.data.1.title', 'Weak fit'));
    }

    /**
     * With nothing scored there is no ranking to apply, so the board falls
     * back to the database order rather than shuffling arbitrarily.
     */
    public function test_recommended_falls_back_to_newest_when_nothing_is_scored(): void
    {
        $student = $this->student();

        $this->posting(['title' => 'Older', 'published_at' => now()->subWeek()]);
        $this->posting(['title' => 'Newer', 'published_at' => now()]);

        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('matchingEnabled', false)
                ->where('projects.data.0.title', 'Newer'));
    }

    public function test_a_row_says_whether_the_business_was_verified(): void
    {
        $student = $this->student();

        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
        $this->posting(['team_id' => $client->current_team_id, 'title' => 'From a verified business']);

        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('projects.data.0.title', 'From a verified business')
                ->where('projects.data.0.isBusinessVerified', true));
    }

    /**
     * An approved student with no credential on file.
     */
    private function student(): User
    {
        return User::factory()->student()->approved()->create();
    }

    /**
     * A student an administrator has cleared to apply for work.
     */
    private function verifiedStudent(): User
    {
        $student = $this->student();

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
     * A live posting open to every student.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function posting(array $attributes = []): Project
    {
        return Project::factory()->create([
            'status' => ProjectStatus::Open,
            'applications_open' => true,
            ...$attributes,
        ]);
    }
}
