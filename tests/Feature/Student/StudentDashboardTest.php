<?php

namespace Tests\Feature\Student;

use App\Enums\ApplicationStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\SiteContentKey;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\Application;
use App\Models\Project;
use App\Models\SiteContent;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The student's home screen. Everything on it is derived from real rows, so a
 * student who has not been hired yet sees empty states rather than someone
 * else's project.
 */
class StudentDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_lands_on_the_platform_dashboard(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('student/dashboard'));
    }

    public function test_a_client_is_still_handed_to_their_own_module(): void
    {
        $client = User::factory()->client()->approved()->create();

        $this->actingAs($client)
            ->get(route('dashboard', ['current_team' => $client->currentTeam]))
            ->assertRedirect(route('client.dashboard', ['current_team' => $client->currentTeam]));
    }

    public function test_a_student_with_no_project_gets_empty_states_rather_than_figures(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('project', null)
                ->where('announcement', null)
                ->has('calendar.days', 42));
    }

    public function test_the_active_project_comes_from_an_accepted_application(): void
    {
        $student = $this->student();
        $project = $this->project(['title' => 'Parrot Inventory System']);

        $this->accept($student, $project);

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('project.title', 'Parrot Inventory System'));
    }

    public function test_an_application_still_pending_is_not_a_project_yet(): void
    {
        $student = $this->student();
        $project = $this->project();

        Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Pending,
        ]);

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('project', null));
    }

    public function test_progress_averages_the_agreements_milestones(): void
    {
        $student = $this->student();
        $project = $this->project(['status' => ProjectStatus::InProgress]);
        $application = $this->accept($student, $project);

        $agreement = Agreement::factory()->active()->create([
            'project_id' => $project->id,
            'application_id' => $application->id,
            'team_id' => $project->team_id,
            'student_id' => $student->id,
            'ends_on' => '2026-05-22',
        ]);

        // Approved, in progress, pending — 100 + 40 + 0 over three.
        foreach ([MilestoneStatus::Approved, MilestoneStatus::InProgress, MilestoneStatus::Pending] as $index => $status) {
            AgreementMilestone::factory()->create([
                'agreement_id' => $agreement->id,
                'position' => $index + 1,
                'status' => $status,
            ]);
        }

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('project.progress', 47)
                ->where('project.dueDate', '22 May 2026')
                ->where('project.statusLabel', 'In progress'));
    }

    public function test_progress_is_a_dash_until_an_agreement_is_signed(): void
    {
        $student = $this->student();
        $project = $this->project(['status' => ProjectStatus::InProgress]);

        $this->accept($student, $project);

        // A posting carries no dates or milestones, and a lifecycle stage is
        // not a percentage. Null renders as a dash, which is honest.
        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('project.progress', null));
    }

    public function test_the_calendar_marks_the_agreed_milestone_dates(): void
    {
        Carbon::setTestNow('2026-03-10');

        $student = $this->student();
        $project = $this->project(['status' => ProjectStatus::InProgress]);
        $application = $this->accept($student, $project);

        $agreement = Agreement::factory()->active()->create([
            'project_id' => $project->id,
            'application_id' => $application->id,
            'team_id' => $project->team_id,
            'student_id' => $student->id,
        ]);

        AgreementMilestone::factory()->create([
            'agreement_id' => $agreement->id,
            'position' => 1,
            'title' => 'Design',
            'starts_on' => '2026-03-09',
            'ends_on' => '2026-03-27',
        ]);

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where(
                    'calendar.days',
                    fn (Collection $days) => $days->firstWhere('date', '2026-03-09')['milestone'] === 'Design starts'
                        && $days->firstWhere('date', '2026-03-27')['milestone'] === 'Design due'
                        && $days->firstWhere('date', '2026-03-10')['milestone'] === null
                ));

        Carbon::setTestNow();
    }

    public function test_the_project_team_lists_accepted_students_only(): void
    {
        $student = $this->student();
        $teammate = $this->student();
        $rejected = $this->student();
        $project = $this->project();

        StudentProfile::factory()->for($teammate)->create(['headline' => 'Backend · database']);

        $this->accept($student, $project);
        $this->accept($teammate, $project);

        Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $rejected->id,
            'status' => ApplicationStatus::Rejected,
        ]);

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('project.team', 2)
                ->where('project.team.1.role', 'Backend · database'));
    }

    public function test_the_calendar_shows_the_current_month(): void
    {
        Carbon::setTestNow('2026-03-10');

        $student = $this->student();

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('calendar.label', 'Mar 2026')
                ->has('calendar.days', 42)
                ->where(
                    'calendar.days',
                    fn (Collection $days) => $days->firstWhere('date', '2026-03-10')['isToday'] === true
                        && $days->firstWhere('date', '2026-03-11')['isToday'] === false
                ));

        Carbon::setTestNow();
    }

    public function test_the_announcements_block_reaches_the_student(): void
    {
        $student = $this->student();

        SiteContent::create([
            'key' => SiteContentKey::Announcements,
            'body' => 'Milestone escrow is now live for extension payments.',
        ]);

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('announcement.body', 'Milestone escrow is now live for extension payments.'));
    }

    public function test_an_empty_announcements_block_is_not_an_announcement(): void
    {
        $student = $this->student();

        SiteContent::create(['key' => SiteContentKey::Announcements, 'body' => '']);

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('announcement', null));
    }

    public function test_a_finished_project_drops_off_the_dashboard(): void
    {
        $student = $this->student();
        $project = $this->project(['status' => ProjectStatus::Completed]);

        $this->accept($student, $project);

        // Delivered work belongs on a history screen, not on today's dashboard.
        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('project', null));
    }

    /**
     * Create an approved student sitting on their personal team.
     */
    private function student(): User
    {
        return User::factory()->student()->approved()->create();
    }

    /**
     * Create a live posting from some business.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function project(array $attributes = []): Project
    {
        return Project::factory()->create([
            'status' => ProjectStatus::InProgress,
            ...$attributes,
        ]);
    }

    /**
     * Put the student on the project the way the platform does it.
     */
    private function accept(User $student, Project $project): Application
    {
        return Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Accepted,
        ]);
    }
}
