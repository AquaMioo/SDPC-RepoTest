<?php

namespace Tests\Feature\Teams;

use App\Actions\Notifications\PresentNotification;
use App\Enums\AgreementStatus;
use App\Enums\ApplicationStatus;
use App\Enums\TeamRole;
use App\Models\Agreement;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\StudentProfile;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Agreements\Concerns\StartsCollaboration;
use Tests\TestCase;

/**
 * What a student gets when they are invited onto a team that is building.
 *
 * Testers, 2026-09-23: an invited member could open Project Management and
 * nothing else. The contract they were working under was closed to them, they
 * were missing from both "Project team" panels, they were not in the chat with
 * the client, and the invitation in their bell did not open anywhere.
 */
class InvitedTeammateAccessTest extends TestCase
{
    use RefreshDatabase;
    use StartsCollaboration;

    public function test_a_teammate_reads_the_contract_their_team_signed(): void
    {
        ['student' => $signer, 'agreement' => $agreement] = $this->collaboration();
        $teammate = $this->teammateOf($signer);

        $this->actingAs($teammate)
            ->get(route('agreements.show', ['current_team' => $teammate->currentTeam, 'agreement' => $agreement]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('agreements/show')
                /* Reading, not signing: they are on neither side of the signature block. */
                ->where('agreement.viewer.party', null)
                ->where('agreement.viewer.canSign', false)
                ->where('agreement.viewer.canRequestChanges', false)
                ->where('agreement.viewer.canEdit', false)
                ->etc());

        $this->actingAs($teammate)
            ->get(route('agreements.contract', ['current_team' => $teammate->currentTeam, 'agreement' => $agreement]))
            ->assertOk();
    }

    public function test_the_contract_is_listed_for_a_teammate_with_the_business_across_the_table(): void
    {
        ['student' => $signer, 'agreement' => $agreement] = $this->collaboration();
        $teammate = $this->teammateOf($signer);

        /* One standing agreement redirects straight to it, so a second makes a list. */
        $this->collaborationFor($signer, AgreementStatus::Active);

        $this->actingAs($teammate)
            ->get(route('agreements.index', ['current_team' => $teammate->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('agreements/index')
                ->has('agreements', 2)
                ->where('agreements.1.id', $agreement->id)
                ->where(
                    'agreements.1.counterparty',
                    $agreement->project->team->clientProfile?->business_name ?? $agreement->project->team->name,
                )
                ->etc());
    }

    public function test_a_teammate_cannot_read_terms_nobody_has_signed(): void
    {
        ['student' => $signer, 'agreement' => $agreement] = $this->collaboration(AgreementStatus::AwaitingSignatures);
        $teammate = $this->teammateOf($signer);

        /* A draft is between the two people whose names go on it. */
        $this->actingAs($teammate)
            ->get(route('agreements.show', ['current_team' => $teammate->currentTeam, 'agreement' => $agreement]))
            ->assertForbidden();
    }

    public function test_a_teammate_cannot_sign_the_contract(): void
    {
        ['student' => $signer, 'agreement' => $agreement] = $this->collaboration(AgreementStatus::AwaitingSignatures);
        $teammate = $this->teammateOf($signer);

        $this->actingAs($teammate)
            ->post(route('agreements.signatures.store', ['current_team' => $teammate->currentTeam, 'agreement' => $agreement]), [
                'signed_name' => $teammate->name,
                'acknowledgements' => [],
            ])
            ->assertForbidden();

        $this->assertSame(0, $agreement->signatures()->count());
    }

    public function test_the_client_dashboard_shows_the_whole_team_on_the_build(): void
    {
        ['client' => $client, 'student' => $signer] = $this->collaboration();
        $teammate = $this->teammateOf($signer, TeamRole::QualityAssurance);

        $this->actingAs($client)
            ->get(route('client.dashboard', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(function ($reload) use ($signer, $teammate) {
                $members = collect($reload->toArray()['props']['projectTeam']);

                $this->assertTrue($members->contains('name', $signer->name));
                $this->assertTrue($members->contains(
                    fn (array $member): bool => $member['name'] === $teammate->name
                        && $member['role'] === TeamRole::QualityAssurance->label(),
                ));
            }));
    }

    public function test_a_teammate_finds_themselves_on_their_own_dashboard(): void
    {
        ['student' => $signer] = $this->collaboration();
        $teammate = $this->teammateOf($signer);

        $this->actingAs($teammate)
            ->get(route('dashboard', ['current_team' => $teammate->currentTeam]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/dashboard')
                ->where('project.team.0.name', $signer->name)
                ->where('project.team.1.name', $teammate->name)
                ->etc());
    }

    public function test_joining_a_team_that_is_building_puts_the_student_in_the_client_chat(): void
    {
        ['student' => $signer, 'agreement' => $agreement] = $this->collaboration();

        $conversation = Conversation::firstOrCreate([
            'project_id' => $agreement->project_id,
            'user_id' => $signer->id,
        ]);

        $newcomer = User::factory()->student()->approved()->create();
        StudentProfile::factory()->for($newcomer)->create();

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $signer->currentTeam->id,
            'email' => $newcomer->email,
            'role' => TeamRole::LeadProgrammer,
            'invited_by' => $signer->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($newcomer)
            ->post(route('invitations.accept', $invitation))
            ->assertRedirect();

        $conversation->refresh();

        $this->assertSame($signer->currentTeam->id, $conversation->student_team_id);
        $this->assertTrue($conversation->members()->whereKey($newcomer->id)->exists());
        $this->assertTrue($conversation->isParticipant($newcomer->refresh()));

        /* And the thread opens for them, rather than 403ing. */
        $this->actingAs($newcomer)
            ->get(route('messages.show', ['current_team' => $newcomer->currentTeam, 'conversation' => $conversation]))
            ->assertOk();
    }

    public function test_a_team_that_was_already_together_is_seated_when_the_client_accepts(): void
    {
        $signer = User::factory()->student()->approved()->create();
        $teammate = $this->teammateOf($signer);

        $client = User::factory()->client()->verifiedBusiness()->create();
        $project = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'created_by' => $client->id,
        ]);

        $application = Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $signer->id,
            'status' => ApplicationStatus::Pending,
        ]);

        $this->actingAs($client)
            ->patch(route('applications.update', [
                'current_team' => $client->currentTeam,
                'application' => $application,
            ]), ['status' => ApplicationStatus::Accepted->value])
            ->assertRedirect();

        $conversation = Conversation::query()
            ->where('project_id', $project->id)
            ->where('user_id', $signer->id)
            ->sole();

        $this->assertTrue($conversation->members()->whereKey($teammate->id)->exists());
    }

    public function test_the_invitation_in_the_bell_opens_the_dashboard(): void
    {
        $leader = User::factory()->student()->create();
        $invitee = User::factory()->student()->create();

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $leader->currentTeam->id,
            'email' => $invitee->email,
            'role' => TeamRole::LeadProgrammer,
            'invited_by' => $leader->id,
            'expires_at' => now()->addDay(),
        ]);

        $invitee->notify(new TeamInvitationNotification($invitation));

        $row = app(PresentNotification::class)->handle(
            $invitee->notifications()->sole(),
            $invitee->currentTeam,
        );

        /*
         * It used to carry no link at all, so an invitation dismissed once was
         * unreachable from the bell — the first place anybody looks for it.
         */
        $this->assertNotNull($row['url']);
        $this->assertStringContainsString('invitation=', (string) $row['url']);
    }

    /**
     * Seat a student on the leader's team.
     */
    private function teammateOf(User $leader, TeamRole $role = TeamRole::LeadProgrammer): User
    {
        $teammate = User::factory()->student()->approved()->create();
        StudentProfile::factory()->for($teammate)->create();

        $leader->currentTeam->members()->attach($teammate, ['role' => $role->value]);
        $teammate->switchTeam($leader->currentTeam);

        return $teammate->refresh();
    }

    /**
     * A second collaboration for the same student, so the list has two rows.
     */
    private function collaborationFor(User $student, AgreementStatus $status): void
    {
        $client = User::factory()->client()->verifiedBusiness()->create();
        $project = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'created_by' => $client->id,
        ]);

        $application = Application::factory()->accepted()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
        ]);

        Agreement::factory()->create([
            'project_id' => $project->id,
            'application_id' => $application->id,
            'team_id' => $client->current_team_id,
            'student_id' => $student->id,
            'status' => $status,
        ]);
    }
}
