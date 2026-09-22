<?php

namespace Tests\Feature\Student;

use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Enums\TeamRole;
use App\Models\Application;
use App\Models\Project;
use App\Models\StudentProfile;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * A teammate is tied to their leader's build.
 *
 * The student who signed holds the project; everyone on the team they lead
 * works on it too. So a teammate is locked the same way the signer is — no
 * applying, no accepting, not on Recruit, cannot leave the team — until the
 * client completes the project, and the dashboard shows them that build.
 */
class TeammateProjectLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_teammate_is_locked_with_the_student_who_holds_the_build(): void
    {
        [$leader, $teammate, $project] = $this->teamOnABuild();
        $free = $this->student();

        $this->assertTrue($leader->isLockedToProject());
        $this->assertTrue($teammate->isLockedToProject());
        $this->assertFalse($free->isLockedToProject());

        $project->update(['status' => ProjectStatus::Completed]);

        $this->assertFalse($leader->fresh()->isLockedToProject());
        $this->assertFalse($teammate->fresh()->isLockedToProject());
    }

    public function test_a_teammate_cannot_apply_for_other_work(): void
    {
        [, $teammate] = $this->teamOnABuild();
        $other = Project::factory()->create(['status' => ProjectStatus::Open]);

        $this->actingAs($teammate)
            ->post(route('student.board.apply', ['current_team' => $teammate->currentTeam, 'project' => $other]), [
                'cover_letter' => 'I have built two inventory systems before and would like to help.',
            ])
            ->assertSessionHasErrors('application');

        $this->assertFalse(Application::query()->where('user_id', $teammate->id)->exists());
    }

    public function test_a_teammate_cannot_accept_a_clients_invitation(): void
    {
        [, $teammate] = $this->teamOnABuild();
        $invitation = Application::factory()->invited()->create(['user_id' => $teammate->id]);

        $this->actingAs($teammate)
            ->post(route('student.applications.accept', ['current_team' => $teammate->currentTeam, 'application' => $invitation]))
            ->assertSessionHasErrors('application');

        $this->assertNotSame(ApplicationStatus::Accepted, $invitation->fresh()->status);
    }

    public function test_a_client_cannot_take_a_teammate_on(): void
    {
        [, $teammate] = $this->teamOnABuild();
        $client = User::factory()->client()->verifiedBusiness()->create();
        $posting = Project::factory()->create(['team_id' => $client->current_team_id, 'status' => ProjectStatus::Open]);
        $application = Application::factory()->create(['project_id' => $posting->id, 'user_id' => $teammate->id]);

        $this->actingAs($client)
            ->patch(route('applications.update', ['current_team' => $client->currentTeam, 'application' => $application]), [
                'status' => ApplicationStatus::Accepted->value,
            ])
            ->assertSessionHasErrors('status');

        $this->assertNotSame(ApplicationStatus::Accepted, $application->fresh()->status);
    }

    public function test_recruit_leaves_out_everyone_on_a_build(): void
    {
        [$leader, $teammate] = $this->teamOnABuild();
        $free = $this->student();
        $client = User::factory()->client()->verifiedBusiness()->create();

        $this->actingAs($client)
            ->get(route('recruit.index', ['current_team' => $client->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->loadDeferredProps('ranking', function (AssertableInertia $reload) use ($leader, $teammate, $free) {
                $ids = collect($reload->toArray()['props']['students']['data'])->pluck('id');

                $this->assertTrue($ids->contains($free->id));
                $this->assertFalse($ids->contains($leader->id));
                $this->assertFalse($ids->contains($teammate->id));
            }));
    }

    public function test_a_teammate_cannot_leave_the_team_mid_build_but_can_after(): void
    {
        [$leader, $teammate, $project] = $this->teamOnABuild();
        $team = $leader->currentTeam;

        $this->actingAs($teammate)->delete(route('teams.leave', $team))->assertForbidden();
        $this->assertTrue($teammate->fresh()->belongsToTeam($team));

        $project->update(['status' => ProjectStatus::Completed]);

        $this->actingAs($teammate)->delete(route('teams.leave', $team))->assertRedirect(route('teams.index'));
        $this->assertFalse($teammate->fresh()->belongsToTeam($team));
    }

    public function test_the_teams_page_says_which_team_is_on_a_build(): void
    {
        [$leader, $teammate] = $this->teamOnABuild();

        $this->actingAs($teammate)
            ->get(route('teams.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('buildingTeamIds', [$leader->current_team_id]));
    }

    public function test_a_student_holding_a_build_cannot_join_another_team(): void
    {
        [$leader] = $this->teamOnABuild(withTeammate: false);
        $otherLeader = $this->student();
        $invitation = TeamInvitation::factory()->create([
            'team_id' => $otherLeader->current_team_id,
            'email' => $leader->email,
            'role' => TeamRole::LeadProgrammer,
            'invited_by' => $otherLeader->id,
        ]);

        $this->actingAs($leader)
            ->post(route('invitations.accept', $invitation))
            ->assertSessionHasErrors(['invitation' => 'You are working on a project, so you cannot join another team until the client completes it.']);

        $this->assertFalse($leader->fresh()->belongsToTeam($otherLeader->currentTeam));
    }

    public function test_the_teammates_dashboard_shows_the_team_build(): void
    {
        [, $teammate, $project] = $this->teamOnABuild();

        $this->actingAs($teammate)
            ->get(route('dashboard', ['current_team' => $teammate->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('project.title', $project->title));
    }

    public function test_a_posting_leaves_the_board_once_a_student_is_accepted(): void
    {
        $viewer = $this->student();
        $taken = Project::factory()->create(['status' => ProjectStatus::Open, 'applications_open' => true]);
        $open = Project::factory()->create(['status' => ProjectStatus::Open, 'applications_open' => true]);
        Application::factory()->accepted()->create(['project_id' => $taken->id]);

        $this->actingAs($viewer)
            ->get(route('student.board.index', ['current_team' => $viewer->currentTeam, 'sort' => 'newest']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->loadDeferredProps('ranking', function (AssertableInertia $reload) use ($taken, $open) {
                $ids = collect($reload->toArray()['props']['projects']['data'])->pluck('id');

                $this->assertTrue($ids->contains($open->id));
                $this->assertFalse($ids->contains($taken->id));
            }));
    }

    /**
     * A leader accepted onto a posting in progress, and a teammate on their team.
     *
     * @return array{0: User, 1: User|null, 2: Project}
     */
    private function teamOnABuild(bool $withTeammate = true): array
    {
        $leader = $this->student();
        $project = Project::factory()->inProgress()->create();

        Application::factory()->accepted()->create([
            'project_id' => $project->id,
            'user_id' => $leader->id,
        ]);

        $teammate = null;

        if ($withTeammate) {
            $teammate = $this->student();
            $leader->currentTeam->members()->attach($teammate, ['role' => TeamRole::Member->value]);
            $teammate->switchTeam($leader->currentTeam);
            $teammate->refresh();
        }

        return [$leader->refresh(), $teammate, $project];
    }

    private function student(): User
    {
        $student = User::factory()->student()->approved()->create();

        StudentProfile::factory()->for($student)->create();

        return $student->refresh();
    }
}
