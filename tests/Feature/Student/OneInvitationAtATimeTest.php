<?php

namespace Tests\Feature\Student;

use App\Actions\Notifications\PresentNotification;
use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Enums\TeamRole;
use App\Models\Application;
use App\Models\Project;
use App\Models\StudentProfile;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Client\InvitationAccepted;
use App\Notifications\Client\InvitationClosed;
use App\Notifications\Teams\InviteeJoinedAnotherTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * A student takes up one invitation, and everybody else who invited them is
 * told.
 *
 * A student works on one project at a time and belongs to one team at a time.
 * Once one invitation is accepted the others can never be, so they are closed
 * and their senders get an email and a bell entry saying the student accepted
 * somebody else's — instead of waiting on an answer that cannot come.
 *
 * Routes are pinned with an explicit current_team; see .ai/rules/feature.md.
 */
class OneInvitationAtATimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepting_one_invitation_closes_the_others_and_tells_those_clients(): void
    {
        Notification::fake();

        $student = $this->student();
        [$chosenClient, $chosen] = $this->invite($student);
        [$otherClient, $other] = $this->invite($student);
        [$shortlistingClient, $shortlisted] = $this->invite($student, ApplicationStatus::Shortlisted);

        /* The student's own application elsewhere is theirs to withdraw, not ours to close. */
        [, $applied] = $this->invite($student, source: ApplicationSource::Applied);

        $this->accept($student, $chosen)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Invitation accepted. Your agreement is ready to review. Your other invitations were closed, and those clients have been told.');

        $this->assertSame(ApplicationStatus::Accepted, $chosen->fresh()->status);
        $this->assertSame(ApplicationStatus::Withdrawn, $other->fresh()->status);
        $this->assertSame(ApplicationStatus::Withdrawn, $shortlisted->fresh()->status);
        $this->assertSame($student->id, $other->fresh()->responded_by);
        $this->assertSame(ApplicationStatus::Pending, $applied->fresh()->status);

        foreach ([[$otherClient, $other], [$shortlistingClient, $shortlisted]] as [$client, $invitation]) {
            Notification::assertSentTo(
                $client,
                InvitationClosed::class,
                fn (InvitationClosed $notification, array $channels): bool => $channels === ['mail', 'database']
                    && $notification->invitation->is($invitation),
            );
        }

        /* The business that was chosen hears the good news, not the closing. */
        Notification::assertSentTo($chosenClient, InvitationAccepted::class);
        Notification::assertNotSentTo($chosenClient, InvitationClosed::class);
    }

    public function test_the_email_says_the_student_accepted_another_clients_invitation(): void
    {
        Notification::fake();

        $student = $this->student();
        [, $chosen] = $this->invite($student);
        [$otherClient, $other] = $this->invite($student);

        $this->accept($student, $chosen)->assertSessionHasNoErrors();

        Notification::assertSentTo($otherClient, InvitationClosed::class, function (InvitationClosed $notification) use ($otherClient, $student, $other): bool {
            $mail = $notification->toMail($otherClient);

            $this->assertSame($student->name.' is no longer available', $mail->subject);
            $this->assertSame($student->name.' has already accepted an invitation from another client.', $mail->introLines[0]);
            $this->assertStringContainsString('Your invitation to '.$other->project->title.' was closed', $mail->introLines[1]);
            $this->assertSame(route('recruit.index', ['current_team' => $otherClient->currentTeam->slug]), $mail->actionUrl);

            return true;
        });
    }

    public function test_the_bell_entry_reads_properly(): void
    {
        $student = $this->student();
        [, $chosen] = $this->invite($student);
        [$otherClient, $other] = $this->invite($student);

        $this->accept($student, $chosen)->assertSessionHasNoErrors();

        /** @var DatabaseNotification $row */
        $row = $otherClient->notifications()->where('type', InvitationClosed::class)->sole();
        $presented = app(PresentNotification::class)->handle($row, $otherClient->currentTeam);

        $this->assertSame($student->name.' is no longer available', $presented['title']);
        $this->assertSame('They have already accepted an invitation from another client. Your invitation to '.$other->project->title.' was closed.', $presented['body']);
    }

    /**
     * Being hired on an application takes the student just as surely, so the
     * invitations close then as well — worded for what actually happened.
     */
    public function test_a_client_accepting_the_students_application_also_closes_their_invitations(): void
    {
        Notification::fake();

        $student = $this->student();
        [$hiringClient, $application] = $this->invite($student, source: ApplicationSource::Applied);
        [$otherClient, $invitation] = $this->invite($student);

        $this->actingAs($hiringClient)
            ->patch(route('applications.update', [
                'current_team' => $hiringClient->currentTeam,
                'application' => $application,
            ]), ['status' => ApplicationStatus::Accepted->value])
            ->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::Withdrawn, $invitation->fresh()->status);

        Notification::assertSentTo($otherClient, InvitationClosed::class, function (InvitationClosed $notification) use ($otherClient, $student): bool {
            return $notification->toMail($otherClient)->introLines[0] === $student->name.' has already been taken on by another client.';
        });
    }

    public function test_declining_an_invitation_leaves_the_others_open(): void
    {
        Notification::fake();

        $student = $this->student();
        [, $declined] = $this->invite($student);
        [, $other] = $this->invite($student);

        $this->actingAs($student)
            ->post(route('student.applications.decline', [
                'current_team' => $student->currentTeam,
                'application' => $declined,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::Pending, $other->fresh()->status);
        Notification::assertNothingSentTo($other->project->team->members->first());
    }

    /**
     * An invitation that arrives after the student was taken on cannot be
     * accepted, so the list says why instead of offering Accept.
     */
    public function test_an_invitation_the_student_cannot_take_says_why(): void
    {
        $student = $this->student();
        $this->holdProject($student);

        [, $late] = $this->invite($student);

        $this->actingAs($student)
            ->get(route('project-management', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('applications', fn ($applications): bool => collect($applications)
                    ->firstWhere('id', $late->id)['canAccept'] === false));
    }

    public function test_a_client_cannot_invite_a_student_who_is_already_taken(): void
    {
        $student = $this->student();
        $this->holdProject($student);

        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
        $project = Project::factory()->create(['team_id' => $client->current_team_id, 'status' => ProjectStatus::Open]);

        $this->actingAs($client)
            ->get(route('students.show', ['current_team' => $client->currentTeam, 'user' => $student]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('isTaken', true));

        $this->actingAs($client)
            ->post(route('projects.invitations.store', [
                'current_team' => $client->currentTeam,
                'project' => $project,
            ]), ['user_id' => $student->id])
            ->assertSessionHasErrors([
                'user_id' => $student->name.' has already accepted an invitation from another client, so they cannot be invited right now. They become available again once that project is finished.',
            ]);

        $this->assertSame(0, $project->applications()->count());
    }

    public function test_a_free_student_can_still_be_invited(): void
    {
        $student = $this->student();

        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
        $project = Project::factory()->create(['team_id' => $client->current_team_id, 'status' => ProjectStatus::Open]);

        $this->actingAs($client)
            ->get(route('students.show', ['current_team' => $client->currentTeam, 'user' => $student]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('isTaken', false));

        $this->actingAs($client)
            ->post(route('projects.invitations.store', [
                'current_team' => $client->currentTeam,
                'project' => $project,
            ]), ['user_id' => $student->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $project->applications()->count());
    }

    /**
     * Team invitations work the same way: a student is on one team, so
     * joining one cancels the rest and tells the students who sent them.
     */
    public function test_joining_a_team_cancels_the_other_team_invitations_and_tells_their_senders(): void
    {
        Notification::fake();

        $student = User::factory()->student()->create(['email' => 'wanted@example.com']);
        [$chosenLead, $chosenTeam, $chosen] = $this->teamInvitation('Wanted@Example.com', 'Byte Builders');
        [$otherLead, $otherTeam, $other] = $this->teamInvitation('wanted@example.com', 'Code Crafters');

        /* Somebody else's invitation is none of this. */
        [, , $unrelated] = $this->teamInvitation('someone-else@example.com', 'Night Owls');

        $this->actingAs($student)
            ->post(route('invitations.accept', $chosen))
            ->assertSessionHasNoErrors();

        $this->assertTrue($student->fresh()->belongsToTeam($chosenTeam));
        $this->assertNotNull($chosen->fresh()->accepted_at);
        $this->assertModelMissing($other);
        $this->assertModelExists($unrelated);

        Notification::assertSentTo(
            $otherLead,
            InviteeJoinedAnotherTeam::class,
            function (InviteeJoinedAnotherTeam $notification, array $channels) use ($otherLead, $otherTeam, $student): bool {
                $mail = $notification->toMail($otherLead);

                $this->assertSame(['mail', 'database'], $channels);
                $this->assertSame($student->name.' joined another team', $mail->subject);
                $this->assertSame($student->name.' has already accepted an invitation from another student and joined Byte Builders.', $mail->introLines[0]);
                $this->assertStringContainsString('Your invitation to '.$otherTeam->name.' was cancelled', $mail->introLines[1]);

                return true;
            },
        );

        Notification::assertNotSentTo($chosenLead, InviteeJoinedAnotherTeam::class);
    }

    public function test_the_team_bell_entry_reads_properly(): void
    {
        $student = User::factory()->student()->create(['email' => 'wanted@example.com']);
        [, , $chosen] = $this->teamInvitation('wanted@example.com', 'Byte Builders');
        [$otherLead, $otherTeam] = $this->teamInvitation('wanted@example.com', 'Code Crafters');

        $this->actingAs($student)->post(route('invitations.accept', $chosen))->assertSessionHasNoErrors();

        $row = $otherLead->notifications()->where('type', InviteeJoinedAnotherTeam::class)->sole();
        $presented = app(PresentNotification::class)->handle($row, $otherLead->currentTeam);

        $this->assertSame($student->name.' joined another team', $presented['title']);
        $this->assertSame('They accepted an invitation to Byte Builders, so your invitation to Code Crafters was cancelled.', $presented['body']);
        $this->assertSame(route('teams.edit', ['team' => $otherTeam->slug]), $presented['url']);
    }

    /**
     * A student with a profile, as the invitation screens expect.
     */
    private function student(): User
    {
        $student = User::factory()->student()->approved()->create();
        StudentProfile::factory()->for($student)->create();

        return $student->fresh();
    }

    /**
     * A business inviting the student onto an open posting of its own.
     *
     * @return array{0: User, 1: Application}
     */
    private function invite(
        User $student,
        ApplicationStatus $status = ApplicationStatus::Pending,
        ApplicationSource $source = ApplicationSource::Invited,
    ): array {
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();

        $project = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'status' => ProjectStatus::Open,
        ]);

        $application = Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => $status,
            'source' => $source,
        ]);

        return [$client->fresh(), $application];
    }

    /**
     * Put the student on an unfinished project already.
     */
    private function holdProject(User $student): void
    {
        Application::factory()->create([
            'project_id' => Project::factory()->create(['status' => ProjectStatus::InProgress])->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Accepted,
        ]);
    }

    private function accept(User $student, Application $invitation): TestResponse
    {
        return $this->actingAs($student)
            ->from(route('project-management', ['current_team' => $student->currentTeam]))
            ->post(route('student.applications.accept', [
                'current_team' => $student->currentTeam,
                'application' => $invitation,
            ]));
    }

    /**
     * A student-led team inviting the given address.
     *
     * @return array{0: User, 1: Team, 2: TeamInvitation}
     */
    private function teamInvitation(string $email, string $teamName): array
    {
        $lead = User::factory()->student()->create();
        $team = Team::factory()->create(['name' => $teamName, 'is_personal' => false]);
        $team->members()->attach($lead, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => $email,
            'role' => TeamRole::Member,
            'invited_by' => $lead->id,
        ]);

        return [$lead->fresh(), $team, $invitation];
    }
}
