<?php

namespace Tests\Feature\Auth;

use App\Enums\TeamRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Auth\Concerns\CompletesRegistration;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use CompletesRegistration, RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_registration_screen_includes_team_invitation_context()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Laravel Team']);
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this->get(route('register', ['invitation' => $invitation->code]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/register')
            ->where('teamInvitation.code', $invitation->code)
            ->where('teamInvitation.teamName', 'Laravel Team'),
        );
    }

    public function test_new_client_users_can_register()
    {
        $response = $this->completeRegistration([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Client->value,
            'business_name' => 'Zenith Solutions Group',
            'terms' => '1',
        ]);

        $this->assertAuthenticated();

        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertSame('Test User', $user->name);
        $this->assertSame(UserRole::Client, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);

        // A client's team is their business, and it carries a profile.
        $team = $user->refresh()->currentTeam;
        $this->assertNotNull($team);
        $this->assertSame('Zenith Solutions Group', $team->name);
        $this->assertDatabaseHas('client_profiles', [
            'team_id' => $team->id,
            'business_name' => 'Zenith Solutions Group',
        ]);

        $response->assertRedirect("/{$team->slug}/dashboard");
    }

    public function test_new_student_users_can_register()
    {
        $this->completeRegistration([
            'first_name' => 'Kristiane',
            'last_name' => 'Dela Pena',
            'email' => 'student@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Student->value,
            'school_email' => '02000123456@sti.edu.ph',
            'terms' => '1',
        ]);

        $this->assertAuthenticated();

        $user = User::where('email', 'student@example.com')->firstOrFail();

        $this->assertSame(UserRole::Student, $user->role);
        $this->assertTrue($user->refresh()->currentTeam?->is_personal);
    }

    public function test_registration_rejects_the_admin_role()
    {
        $response = $this->post(route('register.store'), [
            'first_name' => 'Mallory',
            'last_name' => 'Smith',
            'email' => 'mallory@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Admin->value,
            'terms' => '1',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'mallory@example.com']);
    }

    public function test_registration_requires_accepting_the_terms()
    {
        $response = $this->post(route('register.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'noterms@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Client->value,
            'business_name' => 'Zenith Solutions Group',
        ]);

        $response->assertSessionHasErrors('terms');
        $this->assertGuest();
    }
}
