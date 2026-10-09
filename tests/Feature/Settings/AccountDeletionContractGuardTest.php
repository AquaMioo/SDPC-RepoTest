<?php

namespace Tests\Feature\Settings;

use App\Enums\AgreementStatus;
use App\Enums\TeamRole;
use App\Models\Agreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An account that is a party to a contract between a client and a student
 * cannot be deleted, by its owner or by an administrator. Deleting it would
 * hard-delete the contract with it.
 */
class AccountDeletionContractGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_with_an_active_contract_cannot_delete_their_account(): void
    {
        $student = User::factory()->student()->create();
        Agreement::factory()->active()->create(['student_id' => $student->id]);

        $this->actingAs($student)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors(['password' => 'Your account cannot be deleted while it has an existing contract with a client.'])
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($student->fresh());
        $this->assertAuthenticatedAs($student);
    }

    public function test_a_client_with_a_contract_cannot_delete_their_account(): void
    {
        $client = User::factory()->client()->create();
        Agreement::factory()->create(['team_id' => $client->current_team_id]);

        $this->actingAs($client)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors(['password' => 'Your account cannot be deleted while it has an existing contract with a student.']);

        $this->assertNotNull($client->fresh());
    }

    public function test_a_member_of_the_clients_business_is_a_party_too(): void
    {
        $client = User::factory()->client()->create();
        $colleague = User::factory()->client()->create();
        $client->currentTeam->members()->attach($colleague, ['role' => TeamRole::Member->value]);
        Agreement::factory()->active()->create(['team_id' => $client->current_team_id]);

        $this->actingAs($colleague)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('password');

        $this->assertNotNull($colleague->fresh());
    }

    public function test_a_completed_contract_still_counts(): void
    {
        $student = User::factory()->student()->create();
        Agreement::factory()->create([
            'student_id' => $student->id,
            'status' => AgreementStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->actingAs($student)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('password');

        $this->assertNotNull($student->fresh());
    }

    public function test_a_passwordless_account_hears_it_under_the_code_field(): void
    {
        $student = User::factory()->student()->create(['password' => null]);
        Agreement::factory()->awaitingSignatures()->create(['student_id' => $student->id]);

        $this->actingAs($student)
            ->delete(route('profile.destroy'), ['code' => '123456'])
            ->assertSessionHasErrors(['code' => 'Your account cannot be deleted while it has an existing contract with a client.']);

        $this->assertNotNull($student->fresh());
    }

    public function test_cancelled_and_superseded_versions_do_not_block_deletion(): void
    {
        $student = User::factory()->student()->create();

        foreach ([AgreementStatus::Cancelled, AgreementStatus::Superseded] as $status) {
            Agreement::factory()->create(['student_id' => $student->id, 'status' => $status]);
        }

        $this->actingAs($student)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertNull($student->fresh());
    }

    public function test_someone_elses_contract_does_not_block_deletion(): void
    {
        $student = User::factory()->student()->create();
        Agreement::factory()->active()->create();

        $this->actingAs($student)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertNull($student->fresh());
    }

    public function test_an_admin_cannot_delete_an_account_with_a_contract(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $client = User::factory()->client()->create();
        Agreement::factory()->active()->create([
            'student_id' => $student->id,
            'team_id' => $client->current_team_id,
        ]);

        foreach ([$student, $client] as $party) {
            $this->actingAs($admin)
                ->delete(route('admin.users.destroy', $party))
                ->assertRedirect()
                ->assertInertiaFlash('toast.type', 'error');

            $this->assertNotNull($party->fresh());
        }
    }
}
