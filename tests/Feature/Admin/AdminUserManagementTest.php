<?php

namespace Tests\Feature\Admin;

use App\Enums\CredentialStatus;
use App\Enums\UserStatus;
use App\Models\StudentCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_user_screen_lists_accounts_with_their_role_and_status(): void
    {
        $admin = User::factory()->admin()->create();

        $student = User::factory()->student()->create([
            'name' => 'Ada Lovelace',
            'created_at' => now()->addMinute(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/users')
            ->has('users', 2)
            ->has('statuses', count(UserStatus::cases()))
            ->where('users.0.name', $student->name)
            ->where('users.0.role', 'student')
            ->where('users.0.status', 'pending')
            ->where('users.0.isSelf', false),
        );
    }

    public function test_accounts_created_in_the_same_second_have_a_stable_order(): void
    {
        $admin = User::factory()->admin()->create();

        $sameMoment = now();

        $first = User::factory()->student()->create(['created_at' => $sameMoment]);
        $second = User::factory()->student()->create(['created_at' => $sameMoment]);

        // Without the id tiebreaker these two could swap places between page
        // loads, since created_at alone does not separate them.
        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.0.id', $second->id)
                ->where('users.1.id', $first->id),
            );
    }

    public function test_the_screen_marks_the_signed_in_administrator_as_themselves(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'The Admin']);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        // The screen hides its own status control off the back of this flag.
        $response->assertInertia(fn (Assert $page) => $page
            ->where('users.0.name', 'The Admin')
            ->where('users.0.isSelf', true),
        );
    }

    public function test_the_screen_surfaces_the_latest_credential_status(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create(['created_at' => now()->addMinute()]);

        $this->submissionFor($student, CredentialStatus::Rejected);
        $this->submissionFor($student, CredentialStatus::NeedsReview);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('users.0.credentialStatus', 'needs_review')
            ->where('users.0.credentialStatusLabel', 'Awaiting review'),
        );
    }

    public function test_non_admins_can_not_reach_the_user_screen(): void
    {
        foreach ([User::factory()->student(), User::factory()->client()] as $factory) {
            $this->actingAs($factory->create())
                ->get(route('admin.users.index'))
                ->assertForbidden();
        }
    }

    public function test_guests_can_not_reach_the_user_screen(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect();
    }

    public function test_an_administrator_can_approve_an_account(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.status.update', $student), [
                'status' => UserStatus::Approved->value,
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(UserStatus::Approved, $student->refresh()->status);
    }

    public function test_an_administrator_can_deactivate_an_account(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.status.update', $client), [
                'status' => UserStatus::Deactivated->value,
            ])
            ->assertSessionHasNoErrors();

        $client->refresh();

        $this->assertSame(UserStatus::Deactivated, $client->status);
        $this->assertTrue($client->isDeactivated());
    }

    public function test_an_administrator_can_not_change_their_own_status(): void
    {
        $admin = User::factory()->admin()->create();

        // Deactivating yourself mid-session would leave the portal unreachable,
        // so the request is refused rather than merely hidden in the UI.
        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.status.update', $admin), [
                'status' => UserStatus::Deactivated->value,
            ])
            ->assertForbidden();

        $this->assertSame(UserStatus::Pending, $admin->refresh()->status);
    }

    public function test_the_status_must_be_a_real_one(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.status.update', $student), [
                'status' => 'banished',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(UserStatus::Pending, $student->refresh()->status);
    }

    public function test_a_non_admin_can_not_change_anyone_else_status(): void
    {
        $client = User::factory()->client()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($client)
            ->patch(route('admin.users.status.update', $student), [
                'status' => UserStatus::Approved->value,
            ])
            ->assertForbidden();

        $this->assertSame(UserStatus::Pending, $student->refresh()->status);
    }

    public function test_a_deactivated_account_can_no_longer_sign_in(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.status.update', $student), [
                'status' => UserStatus::Deactivated->value,
            ]);

        $this->post(route('logout'));

        $this->post(route('login.store'), [
            'email' => $student->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    /**
     * Store a submission the way StudentCredentialController does.
     */
    private function submissionFor(User $student, CredentialStatus $status): StudentCredential
    {
        $credential = new StudentCredential;

        $credential->forceFill([
            'user_id' => $student->id,
            'school' => 'City College of Technology',
            'disk' => 'local',
            'path' => 'student-credentials/'.$student->id.'/student-id.jpg',
            'original_name' => 'student-id.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'checksum' => str_repeat('a', 64),
            'status' => $status,
        ])->save();

        return $credential;
    }
}
