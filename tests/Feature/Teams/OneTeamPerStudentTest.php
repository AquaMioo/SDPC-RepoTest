<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A student is on exactly one team.
 *
 * "You've joined the team": accepting an invitation replaces the student's own
 * team when nobody else is on it. "You've created the team": a student who
 * leads a group others joined stays with it and cannot accept elsewhere. Two
 * teams at once is never allowed.
 */
class OneTeamPerStudentTest extends TestCase
{
    use RefreshDatabase;

    public function test_joining_a_team_replaces_the_students_own_solo_team(): void
    {
        [$leader, $team] = $this->groupLedBy();
        $joiner = User::factory()->student()->create();
        $ownTeam = $joiner->currentTeam;

        // Something the solo team had sent, and a thread it was attached to.
        $ownTeam->invitations()->create([
            'email' => 'friend@example.com',
            'role' => TeamRole::LeadProgrammer,
            'invited_by' => $joiner->id,
            'expires_at' => now()->addDay(),
        ]);
        $thread = Conversation::factory()->create(['user_id' => $joiner->id, 'student_team_id' => $ownTeam->id]);

        $this->actingAs($joiner)
            ->post(route('invitations.accept', $this->invite($team, $leader, $joiner)))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', ['current_team' => $team->slug]));

        $joiner->refresh();

        $this->assertSame([$team->id], $joiner->teams()->pluck('teams.id')->all());
        $this->assertSame($team->id, $joiner->current_team_id);
        $this->assertSoftDeleted($ownTeam);
        $this->assertSame(0, $ownTeam->invitations()->count());
        $this->assertNull($thread->refresh()->student_team_id);
    }

    public function test_a_student_who_leads_a_group_cannot_join_another_team(): void
    {
        [$leader, $team] = $this->groupLedBy();
        [$otherLeader, $otherTeam] = $this->groupLedBy();

        $this->actingAs($otherLeader)
            ->post(route('invitations.accept', $this->invite($team, $leader, $otherLeader)))
            ->assertSessionHasErrors(['invitation' => 'You lead the team "'.$otherTeam->name.'", and others have joined you. A student can only be on one team, so you cannot join another while you lead yours.']);

        $this->assertFalse($otherLeader->fresh()->belongsToTeam($team));
        $this->assertTrue($otherLeader->fresh()->belongsToTeam($otherTeam));
        $this->assertNotSoftDeleted($otherTeam);
    }

    public function test_a_student_already_on_someone_elses_team_cannot_join_another(): void
    {
        [$leader, $team] = $this->groupLedBy();
        [, $otherTeam, $member] = $this->groupLedBy();

        $this->actingAs($member)
            ->post(route('invitations.accept', $this->invite($team, $leader, $member)))
            ->assertSessionHasErrors('invitation');

        $this->assertFalse($member->fresh()->belongsToTeam($team));
        $this->assertTrue($member->fresh()->belongsToTeam($otherTeam));
    }

    public function test_a_team_lead_is_told_up_front_when_the_invitee_cannot_join(): void
    {
        [$leader, $team] = $this->groupLedBy();
        [$otherLeader] = $this->groupLedBy();
        [, , $otherMember] = $this->groupLedBy();
        $client = User::factory()->client()->create();

        foreach ([$otherLeader, $otherMember, $client] as $invitee) {
            $this->actingAs($leader)
                ->post(route('teams.invitations.store', $team), [
                    'email' => $invitee->email,
                    'role' => TeamRole::LeadProgrammer->value,
                ])
                ->assertSessionHasErrors('email');
        }

        $this->assertSame(0, $team->invitations()->count());
    }

    public function test_leaving_the_joined_team_gives_the_student_a_team_of_their_own(): void
    {
        [, $team, $member] = $this->groupLedBy();

        $this->actingAs($member)
            ->delete(route('teams.leave', $team))
            ->assertRedirect(route('teams.index'));

        $member->refresh();
        $ownTeam = $member->currentTeam;

        $this->assertFalse($member->belongsToTeam($team));
        $this->assertNotNull($ownTeam);
        $this->assertTrue($member->ownsTeam($ownTeam));
        $this->assertSame(1, $member->teams()->count());
    }

    public function test_being_removed_gives_the_student_a_team_of_their_own(): void
    {
        [$leader, $team, $member] = $this->groupLedBy();

        // A team of two: the leader's vote is everyone's.
        $this->actingAs($leader)
            ->delete(route('teams.members.destroy', [$team, $member]));

        $member->refresh();

        $this->assertFalse($member->belongsToTeam($team));
        $this->assertNotNull($member->currentTeam);
        $this->assertTrue($member->ownsTeam($member->currentTeam));
        $this->assertSame(1, $member->teams()->count());
    }

    /**
     * A group chat is a team of four plus the client: five people. A member
     * who leaves takes their own threads out of the team's hands, or the chat
     * would hold them plus a full team once their seat was filled — six.
     */
    public function test_a_group_chat_never_holds_more_than_five_people(): void
    {
        [$leader, $team, $member] = $this->groupLedBy();
        $this->fillTeam($team);

        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['team_id' => $client->current_team_id]);

        $leaderThread = Conversation::factory()->create([
            'project_id' => $project->id,
            'user_id' => $leader->id,
            'student_team_id' => $team->id,
        ]);
        $memberThread = Conversation::factory()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'student_team_id' => $team->id,
        ]);

        $this->assertSame(Team::MAX_MEMBERS + 1, $leaderThread->participants()->count());
        $this->assertSame(Team::MAX_MEMBERS + 1, $memberThread->participants()->count());

        $this->actingAs($member)->delete(route('teams.leave', $team));

        // Somebody new takes the seat, so the team is four again.
        $team->members()->attach(User::factory()->student()->create(), ['role' => TeamRole::SystemAnalyst->value]);

        $this->assertNull($memberThread->fresh()->student_team_id);
        $this->assertSame(
            [$member->id, $client->id],
            $memberThread->fresh()->participants()->pluck('id')->all(),
        );
        $this->assertFalse($memberThread->fresh()->isParticipant($leader));

        // The team's own chat is untouched, and still five.
        $this->assertSame($team->id, $leaderThread->fresh()->student_team_id);
        $this->assertSame(Team::MAX_MEMBERS + 1, $leaderThread->fresh()->participants()->count());
    }

    public function test_being_voted_off_takes_the_students_threads_out_of_the_group_chat(): void
    {
        [$leader, $team, $member] = $this->groupLedBy();
        $thread = Conversation::factory()->create(['user_id' => $member->id, 'student_team_id' => $team->id]);

        // A team of two: the leader's vote is everyone's.
        $this->actingAs($leader)->delete(route('teams.members.destroy', [$team, $member]));

        $this->assertFalse($member->fresh()->belongsToTeam($team));
        $this->assertNull($thread->fresh()->student_team_id);
    }

    /**
     * Threads left attached by students who departed before the release
     * existed are brought back to five by the one-time migration.
     */
    public function test_existing_group_chats_of_students_who_left_are_released(): void
    {
        [$leader, $team] = $this->groupLedBy();
        $departed = User::factory()->student()->create();

        $stale = Conversation::factory()->create(['user_id' => $departed->id, 'student_team_id' => $team->id]);
        $kept = Conversation::factory()->create(['user_id' => $leader->id, 'student_team_id' => $team->id]);
        $solo = Conversation::factory()->create(['user_id' => $departed->id]);

        $migration = require database_path('migrations/2026_09_16_133431_release_group_chats_of_students_who_left_the_team.php');
        $migration->up();

        $this->assertNull($stale->fresh()->student_team_id);
        $this->assertSame($team->id, $kept->fresh()->student_team_id);
        $this->assertNull($solo->fresh()->student_team_id);
    }

    public function test_the_team_page_says_whether_the_student_joined_or_created_it(): void
    {
        [$leader, $team, $member] = $this->groupLedBy();

        $this->actingAs($member)
            ->get(route('teams.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('membership.kind', 'joined')
                ->where('membership.team', $team->name)
                ->where('membership.lead', $leader->name));

        $this->actingAs($leader)
            ->get(route('teams.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('membership.kind', 'created')
                ->where('membership.memberCount', 2)
                ->where('membership.lead', null));
    }

    /**
     * Accounts that joined a team before the rule existed still carry their
     * own solo team. The one-time migration applies the rule to them, and
     * leaves anything it cannot safely decide alone.
     */
    public function test_existing_double_memberships_are_brought_into_line(): void
    {
        [$leader, $team] = $this->groupLedBy();

        // Joined the old way: still on their own solo team, still pointed at it.
        $joiner = User::factory()->student()->create();
        $ownTeam = $joiner->currentTeam;
        $team->members()->attach($joiner, ['role' => TeamRole::LeadProgrammer->value]);

        // Leads a group of their own and also sits on the leader's team.
        [$tangled, $tangledTeam] = $this->groupLedBy();
        $team->members()->attach($tangled, ['role' => TeamRole::SystemAnalyst->value]);

        $migration = require database_path('migrations/2026_09_16_125649_dissolve_solo_teams_of_students_who_joined_another.php');
        $migration->up();

        $joiner->refresh();
        $this->assertSoftDeleted($ownTeam);
        $this->assertSame([$team->id], $joiner->teams()->pluck('teams.id')->all());
        $this->assertSame($team->id, $joiner->current_team_id);

        $this->assertNotSoftDeleted($tangledTeam);
        $this->assertSame(2, $tangled->fresh()->teams()->count());
        $this->assertSame(0, $leader->fresh()->teams()->where('teams.id', '!=', $team->id)->count());
    }

    public function test_the_leader_cannot_leave_their_own_team(): void
    {
        [$leader, $team] = $this->groupLedBy();

        $this->actingAs($leader)
            ->delete(route('teams.leave', $team))
            ->assertForbidden();
    }

    /**
     * A student's own team with one other student who joined it.
     *
     * The member is set up the way JoinTeam leaves them: on this team only.
     *
     * @return array{0: User, 1: Team, 2: User}
     */
    private function groupLedBy(): array
    {
        $leader = User::factory()->student()->create();
        $team = $leader->currentTeam;

        $member = User::factory()->student()->create();
        $ownTeam = $member->currentTeam;

        $team->members()->attach($member, ['role' => TeamRole::LeadProgrammer->value]);
        $member->switchTeam($team);
        $ownTeam->memberships()->delete();
        $ownTeam->delete();

        return [$leader->refresh(), $team->refresh(), $member->refresh()];
    }

    /**
     * Seat students on the team until it holds Team::MAX_MEMBERS.
     */
    private function fillTeam(Team $team): void
    {
        while ($team->members()->count() < Team::MAX_MEMBERS) {
            $team->members()->attach(User::factory()->student()->create(), ['role' => TeamRole::QualityAssurance->value]);
        }
    }

    private function invite(Team $team, User $inviter, User $invitee): TeamInvitation
    {
        return TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => $invitee->email,
            'role' => TeamRole::LeadProgrammer,
            'invited_by' => $inviter->id,
        ]);
    }
}
