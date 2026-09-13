<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_member_roles_can_be_updated_by_owners()
    {
        $owner = User::factory()->student()->create();
        $member = User::factory()->student()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($owner)
            ->patch(route('teams.members.update', [$team, $member]), [
                'role' => TeamRole::ProjectManager->value,
            ]);

        $response->assertRedirect(route('teams.edit', $team));

        $this->assertEquals(
            TeamRole::ProjectManager->value,
            $team->members()->where('user_id', $member->id)->first()->pivot->role->value,
        );
    }

    public function test_team_member_roles_cannot_be_updated_by_non_owners()
    {
        $owner = User::factory()->student()->create();
        $admin = User::factory()->student()->create();
        $member = User::factory()->student()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($admin)
            ->patch(route('teams.members.update', [$team, $member]), [
                'role' => TeamRole::ProjectManager->value,
            ]);

        $response->assertForbidden();
    }

    public function test_team_members_can_be_removed_by_owners()
    {
        $owner = User::factory()->student()->create();
        $member = User::factory()->student()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('teams.members.destroy', [$team, $member]));

        $response->assertRedirect(route('teams.edit', $team));

        $this->assertFalse($member->fresh()->belongsToTeam($team));
    }

    public function test_team_members_cannot_be_removed_by_non_owners()
    {
        $owner = User::factory()->student()->create();
        $admin = User::factory()->student()->create();
        $member = User::factory()->student()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        /*
         * Any member may now vote, so this is accepted rather than refused —
         * but it is one vote of the two this team needs, and the member stays.
         * The rule the test was written for still holds: nobody is removed on
         * one person's say-so.
         */
        $this->actingAs($admin)
            ->delete(route('teams.members.destroy', [$team, $member]))
            ->assertRedirect(route('teams.edit', $team));

        $this->assertTrue($member->fresh()->belongsToTeam($team));
    }

    /**
     * Everyone except the person being removed has to agree.
     */
    public function test_a_member_leaves_only_once_the_whole_team_agrees(): void
    {
        $owner = User::factory()->student()->create();
        $admin = User::factory()->student()->create();
        $member = User::factory()->student()->create();
        $team = Team::factory()->create(['is_personal' => false]);

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this->actingAs($admin)
            ->delete(route('teams.members.destroy', [$team, $member]));

        $this->assertTrue($member->fresh()->belongsToTeam($team), 'One vote of two is not enough.');

        $this->actingAs($owner)
            ->delete(route('teams.members.destroy', [$team, $member]));

        $this->assertFalse($member->fresh()->belongsToTeam($team));

        /* The votes go with them, so a later one starts from nothing. */
        $this->assertSame(0, $team->removalVotes()->count());
    }

    /**
     * Voting twice must not carry a removal on one person's own.
     */
    public function test_a_member_cannot_vote_twice(): void
    {
        $owner = User::factory()->student()->create();
        $admin = User::factory()->student()->create();
        $member = User::factory()->student()->create();
        $team = Team::factory()->create(['is_personal' => false]);

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($admin)
                ->delete(route('teams.members.destroy', [$team, $member]));
        }

        $this->assertTrue($member->fresh()->belongsToTeam($team));
        $this->assertSame(1, $team->removalVotes()->count());
    }

    /**
     * Somebody outside the team has no say in who is on it.
     */
    public function test_a_stranger_cannot_vote(): void
    {
        $owner = User::factory()->student()->create();
        $member = User::factory()->student()->create();
        $stranger = User::factory()->student()->create();
        $team = Team::factory()->create(['is_personal' => false]);

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this->actingAs($stranger)
            ->delete(route('teams.members.destroy', [$team, $member]))
            ->assertForbidden();

        $this->assertTrue($member->fresh()->belongsToTeam($team));
    }

    /**
     * In a team of two "everyone except the target" is one person, so the
     * remaining member still decides alone and nothing feels ceremonial.
     */
    public function test_a_team_of_two_still_removes_on_one_vote(): void
    {
        $owner = User::factory()->student()->create();
        $member = User::factory()->student()->create();
        $team = Team::factory()->create(['is_personal' => false]);

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this->actingAs($owner)
            ->delete(route('teams.members.destroy', [$team, $member]));

        $this->assertFalse($member->fresh()->belongsToTeam($team));
    }

    public function test_team_owner_cannot_be_removed()
    {
        $owner = User::factory()->student()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('teams.members.destroy', [$team, $owner]));

        $response->assertForbidden();

        $this->assertTrue($owner->fresh()->belongsToTeam($team));
    }

    public function test_team_member_role_cannot_be_set_to_owner()
    {
        $owner = User::factory()->student()->create();
        $member = User::factory()->student()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($owner)
            ->patch(route('teams.members.update', [$team, $member]), [
                'role' => TeamRole::Owner->value,
            ]);

        $response->assertSessionHasErrors('role');

        $this->assertEquals(
            TeamRole::Member->value,
            $team->members()->where('user_id', $member->id)->first()->pivot->role->value,
        );
    }

    public function test_removed_member_current_team_is_set_to_personal_team()
    {
        $owner = User::factory()->student()->create();
        $member = User::factory()->student()->create();
        $personalTeam = $member->personalTeam();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $member->update(['current_team_id' => $team->id]);

        $this
            ->actingAs($owner)
            ->delete(route('teams.members.destroy', [$team, $member]));

        $this->assertEquals($personalTeam->id, $member->fresh()->current_team_id);
    }
}
