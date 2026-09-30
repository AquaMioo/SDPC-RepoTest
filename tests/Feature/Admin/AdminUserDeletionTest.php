<?php

namespace Tests\Feature\Admin;

use App\Enums\TeamRole;
use App\Enums\UserStatus;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User Account Management: one Deactivate / Reactivate toggle and a Delete
 * that permanently removes the account (testers, 2026-10-01).
 */
class AdminUserDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_deletes_a_student_and_their_own_team(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $team = $student->currentTeam;

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $student))
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertDatabaseMissing('users', ['id' => $student->id]);
        $this->assertNull(Team::withTrashed()->find($team->id));
    }

    public function test_deleting_a_client_removes_their_business_and_its_postings(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['team_id' => $client->current_team_id]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $client));

        $this->assertDatabaseMissing('users', ['id' => $client->id]);
        $this->assertNull(Team::withTrashed()->find($client->current_team_id));
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_a_team_other_people_are_on_is_left_to_them(): void
    {
        $admin = User::factory()->admin()->create();
        $leader = User::factory()->student()->create();
        $mate = User::factory()->student()->create();
        $team = $leader->currentTeam;
        $team->members()->attach($mate, ['role' => TeamRole::LeadProgrammer->value]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $leader));

        $this->assertDatabaseMissing('users', ['id' => $leader->id]);
        $this->assertNotNull(Team::find($team->id));
        $this->assertTrue($mate->fresh()->belongsToTeam($team));
    }

    public function test_an_admin_cannot_delete_themselves_or_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $otherAdmin))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id]);
    }

    public function test_only_an_admin_can_delete_accounts(): void
    {
        $client = User::factory()->client()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($client)
            ->delete(route('admin.users.destroy', $student))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $student->id]);
    }

    public function test_the_toggle_deactivates_and_reactivates(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.status.update', $student), ['status' => UserStatus::Deactivated->value])
            ->assertSessionHasNoErrors();

        $this->assertSame(UserStatus::Deactivated, $student->fresh()->status);

        $this->actingAs($admin)
            ->patch(route('admin.users.status.update', $student), ['status' => UserStatus::Approved->value])
            ->assertSessionHasNoErrors();

        $this->assertSame(UserStatus::Approved, $student->fresh()->status);
    }
}
