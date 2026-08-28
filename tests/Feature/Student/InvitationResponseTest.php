<?php

namespace Tests\Feature\Student;

use App\Enums\AgreementStatus;
use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Models\Application;
use App\Models\Project;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\Client\InvitationAccepted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Whoever did not open the conversation is the one who answers it.
 *
 * A student applying is asking the client to say yes, and the client decides.
 * A client inviting has already said yes, so the student decides — and until
 * this existed an invited student could only Withdraw, which meant nobody
 * could take an invitation up at all.
 *
 * Routes are pinned with an explicit current_team; see .ai/rules/feature.md.
 */
class InvitationResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_invited_student_is_offered_the_decision(): void
    {
        [$client, $student, $project] = $this->invitation();

        $this->actingAs($student)
            ->get(route('student.workflow', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('applications', 1)
                ->where('applications.0.awaitsMyDecision', true)
                /* There is nothing to take back from a conversation you did not open. */
                ->where('applications.0.canWithdraw', false));
    }

    public function test_a_student_who_applied_is_not_offered_the_decision(): void
    {
        [$client, $student, $project] = $this->invitation(source: ApplicationSource::Applied);

        $this->actingAs($student)
            ->get(route('student.workflow', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('applications.0.awaitsMyDecision', false)
                ->where('applications.0.canWithdraw', true));
    }

    public function test_accepting_an_invitation_puts_the_student_on_the_project(): void
    {
        [$client, $student, $project, $application] = $this->invitation(withApplication: true);

        $this->actingAs($student)
            ->from(route('student.workflow', ['current_team' => $student->currentTeam]))
            ->post(route('student.applications.accept', [
                'current_team' => $student->currentTeam,
                'application' => $application,
            ]))
            ->assertSessionHasNoErrors();

        $application->refresh();

        $this->assertSame(ApplicationStatus::Accepted, $application->status);
        /* The student decided it, so the student is the responder. */
        $this->assertSame($student->id, $application->responded_by);
        $this->assertNotNull($application->responded_at);
    }

    public function test_accepting_drafts_the_agreement_the_two_sides_sign(): void
    {
        [$client, $student, $project, $application] = $this->invitation(withApplication: true);

        $this->actingAs($student)->post(route('student.applications.accept', [
            'current_team' => $student->currentTeam,
            'application' => $application,
        ]));

        $agreement = $application->refresh()->agreement;

        $this->assertNotNull($agreement);
        $this->assertSame(AgreementStatus::Draft, $agreement->status);
        $this->assertSame($student->id, $agreement->student_id);
    }

    public function test_accepting_tells_the_business_that_asked(): void
    {
        Notification::fake();

        [$client, $student, $project, $application] = $this->invitation(withApplication: true);

        $this->actingAs($student)->post(route('student.applications.accept', [
            'current_team' => $student->currentTeam,
            'application' => $application,
        ]));

        Notification::assertSentTo(
            $client,
            InvitationAccepted::class,
            fn (InvitationAccepted $notification): bool => $notification->application->is($application),
        );
    }

    public function test_declining_closes_the_invitation(): void
    {
        [$client, $student, $project, $application] = $this->invitation(withApplication: true);

        $this->actingAs($student)
            ->post(route('student.applications.decline', [
                'current_team' => $student->currentTeam,
                'application' => $application,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::Withdrawn, $application->refresh()->status);
        $this->assertNull($application->agreement);
    }

    public function test_a_student_can_not_answer_somebody_elses_invitation(): void
    {
        [$client, $student, $project, $application] = $this->invitation(withApplication: true);

        $stranger = User::factory()->student()->approved()->create();
        StudentProfile::factory()->for($stranger)->create();

        $this->actingAs($stranger)
            ->post(route('student.applications.accept', [
                'current_team' => $stranger->currentTeam,
                'application' => $application,
            ]))
            ->assertForbidden();

        $this->assertSame(ApplicationStatus::Pending, $application->refresh()->status);
    }

    public function test_a_student_can_not_accept_an_application_they_sent_themselves(): void
    {
        [$client, $student, $project, $application] = $this->invitation(
            source: ApplicationSource::Applied,
            withApplication: true,
        );

        $this->actingAs($student)
            ->post(route('student.applications.accept', [
                'current_team' => $student->currentTeam,
                'application' => $application,
            ]))
            ->assertForbidden();

        $this->assertSame(ApplicationStatus::Pending, $application->refresh()->status);
    }

    public function test_the_client_can_not_accept_the_invitation_they_sent(): void
    {
        [$client, $student, $project, $application] = $this->invitation(withApplication: true);

        /*
         * The whole point: a business that could accept on the student's
         * behalf would put somebody on a project they never agreed to.
         */
        $this->actingAs($client)
            ->patch(route('applications.update', [
                'current_team' => $client->currentTeam,
                'application' => $application,
            ]), ['status' => ApplicationStatus::Accepted->value])
            ->assertForbidden();

        $this->assertSame(ApplicationStatus::Pending, $application->refresh()->status);
    }

    public function test_the_client_can_still_accept_an_application_the_student_sent(): void
    {
        [$client, $student, $project, $application] = $this->invitation(
            source: ApplicationSource::Applied,
            withApplication: true,
        );

        $this->actingAs($client)
            ->from(route('projects.applicants.index', [
                'current_team' => $client->currentTeam,
                'project' => $project,
            ]))
            ->patch(route('applications.update', [
                'current_team' => $client->currentTeam,
                'application' => $application,
            ]), ['status' => ApplicationStatus::Accepted->value])
            ->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::Accepted, $application->refresh()->status);
    }

    public function test_a_student_already_building_something_can_not_take_a_second_project(): void
    {
        [$client, $student, $project, $application] = $this->invitation(withApplication: true);

        /* Already accepted onto an unfinished project elsewhere. */
        Application::factory()->create([
            'project_id' => Project::factory()->create(['status' => ProjectStatus::InProgress])->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Accepted,
        ]);

        $this->actingAs($student)
            ->from(route('student.workflow', ['current_team' => $student->currentTeam]))
            ->post(route('student.applications.accept', [
                'current_team' => $student->currentTeam,
                'application' => $application,
            ]))
            ->assertSessionHasErrors('application');

        $this->assertSame(ApplicationStatus::Pending, $application->refresh()->status);
    }

    /**
     * A client, a student, a posting, and the row between them.
     *
     * @return array{0: User, 1: User, 2: Project, 3?: Application}
     */
    private function invitation(
        ApplicationSource $source = ApplicationSource::Invited,
        bool $withApplication = false,
    ): array {
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
        $student = User::factory()->student()->approved()->create();
        StudentProfile::factory()->for($student)->create();

        $project = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'status' => ProjectStatus::Open,
        ]);

        $application = Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Pending,
            'source' => $source,
        ]);

        return $withApplication
            ? [$client->fresh(), $student->fresh(), $project, $application]
            : [$client->fresh(), $student->fresh(), $project];
    }
}
