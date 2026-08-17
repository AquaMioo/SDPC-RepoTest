<?php

namespace Tests\Feature\Student;

use App\Enums\ApplicationStatus;
use App\Enums\CredentialStatus;
use App\Enums\ProjectStatus;
use App\Models\Application;
use App\Models\Project;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A student builds one project at a time.
 *
 * The platform exists to bridge a student to a client, not to stack work on
 * whoever answers first. Applying and being accepted are the two doors into
 * work, so both answer to the cap — the mirror of the client's one-posting
 * limit.
 */
class WorkLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_free_student_can_apply(): void
    {
        $student = $this->student();
        $project = Project::factory()->create(['status' => ProjectStatus::Open]);

        $this->actingAs($student)
            ->post($this->applyUrl($student, $project), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Application::query()->count());
    }

    public function test_a_student_building_something_cannot_apply_for_more(): void
    {
        $student = $this->student();
        $this->putToWork($student);

        $other = Project::factory()->create(['status' => ProjectStatus::Open]);

        $this->actingAs($student)
            ->from($this->boardUrl($student))
            ->post($this->applyUrl($student, $other), $this->payload())
            ->assertSessionHasErrors('application');

        $this->assertSame(1, Application::query()->count());
    }

    #[DataProvider('finishedStateProvider')]
    public function test_finishing_the_build_lets_them_take_the_next_one(string $state): void
    {
        $student = $this->student();
        $project = $this->putToWork($student);

        $project->update(['status' => ProjectStatus::from($state)]);

        $next = Project::factory()->create(['status' => ProjectStatus::Open]);

        $this->actingAs($student)
            ->post($this->applyUrl($student, $next), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Application::query()->count());
    }

    public function test_a_client_cannot_accept_a_student_who_is_already_building(): void
    {
        $student = $this->student();
        $this->putToWork($student);

        // The other half of the cap. Blocking the student's own apply is not
        // enough — acceptance is the moment work actually starts.
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
        $project = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'status' => ProjectStatus::Open,
        ]);
        $application = Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Pending,
        ]);

        $this->actingAs($client)
            ->from(route('projects.applicants.index', [
                'current_team' => $client->currentTeam,
                'project' => $project,
            ]))
            ->patch(route('applications.update', [
                'current_team' => $client->currentTeam,
                'application' => $application,
            ]), ['status' => ApplicationStatus::Accepted->value])
            ->assertSessionHasErrors('status');

        $this->assertSame(ApplicationStatus::Pending, $application->refresh()->status);
        $this->assertSame(ProjectStatus::Open, $project->refresh()->status);
    }

    public function test_a_busy_student_can_still_be_shortlisted(): void
    {
        $student = $this->student();
        $this->putToWork($student);

        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
        $project = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'status' => ProjectStatus::Open,
        ]);
        $application = Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Pending,
        ]);

        $this->actingAs($client)
            ->from(route('projects.applicants.index', [
                'current_team' => $client->currentTeam,
                'project' => $project,
            ]))
            ->patch(route('applications.update', [
                'current_team' => $client->currentTeam,
                'application' => $application,
            ]), ['status' => ApplicationStatus::Shortlisted->value])
            ->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::Shortlisted, $application->refresh()->status);
    }

    public function test_a_rejected_application_never_held_the_slot(): void
    {
        $student = $this->student();
        $project = Project::factory()->create(['status' => ProjectStatus::Open]);

        Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Rejected,
        ]);

        $this->actingAs($student)
            ->post($this->applyUrl($student, Project::factory()->create([
                'status' => ProjectStatus::Open,
            ])), $this->payload())
            ->assertSessionHasNoErrors();
    }

    public function test_the_board_tells_a_busy_student_why_applying_is_shut(): void
    {
        $student = $this->student();
        $this->putToWork($student);

        $this->actingAs($student)
            ->get($this->boardUrl($student))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('holdsProjectInHand', true)
                ->where('canApply', true));
    }

    public function test_a_free_student_sees_an_open_board(): void
    {
        $this->actingAs($student = $this->student())
            ->get($this->boardUrl($student))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('holdsProjectInHand', false));
    }

    /**
     * The statuses that hand the student's slot back.
     *
     * @return array<string, array{string}>
     */
    public static function finishedStateProvider(): array
    {
        return [
            'completed' => [ProjectStatus::Completed->value],
            'closed' => [ProjectStatus::Closed->value],
            'archived' => [ProjectStatus::Archived->value],
        ];
    }

    /**
     * Put the student on a live build, the way acceptance would.
     */
    private function putToWork(User $student): Project
    {
        $project = Project::factory()->inProgress()->create();

        Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Accepted,
        ]);

        return $project;
    }

    /**
     * A student cleared to apply for work.
     */
    private function student(): User
    {
        $student = User::factory()->student()->approved()->create();

        StudentProfile::factory()->for($student)->create();

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

    private function boardUrl(User $student): string
    {
        return route('student.board.index', ['current_team' => $student->currentTeam]);
    }

    private function applyUrl(User $student, Project $project): string
    {
        return route('student.board.apply', [
            'current_team' => $student->currentTeam,
            'project' => $project,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return ['cover_letter' => 'I have built two inventory systems before.'];
    }
}
