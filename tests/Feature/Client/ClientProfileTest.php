<?php

namespace Tests\Feature\Client;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\ClientProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_team_has_one_business_profile(): void
    {
        $team = Team::factory()->create();
        $profile = ClientProfile::factory()->create(['team_id' => $team->id]);

        $this->assertTrue($team->clientProfile->is($profile));
        $this->assertTrue($profile->team->is($team));
    }

    public function test_a_team_cannot_have_two_business_profiles(): void
    {
        $team = Team::factory()->create();
        ClientProfile::factory()->create(['team_id' => $team->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        ClientProfile::factory()->create(['team_id' => $team->id]);
    }

    public function test_completion_reflects_only_the_fields_that_are_filled(): void
    {
        $profile = ClientProfile::factory()->create(array_merge(
            ['business_name' => 'Javier Hardware'],
            array_fill_keys(
                array_diff(ClientProfile::COMPLETION_FIELDS, ['business_name']),
                null,
            ),
        ));

        $this->assertSame(9, $profile->completionPercentage());
    }

    public function test_completion_reaches_one_hundred_when_every_field_is_filled(): void
    {
        $profile = ClientProfile::factory()->verified()->create([
            'logo_path' => 'logos/business.png',
        ]);

        $this->assertSame(100, $profile->completionPercentage());
    }

    public function test_a_new_profile_starts_unverified(): void
    {
        $profile = ClientProfile::factory()->create();

        $this->assertSame(VerificationStatus::Unverified, $profile->verification_status);
        $this->assertFalse($profile->isVerified());
    }

    public function test_a_verified_profile_reports_as_verified(): void
    {
        $profile = ClientProfile::factory()->verified()->create();

        $this->assertTrue($profile->isVerified());
        $this->assertNotNull($profile->verified_at);
    }

    public function test_deleting_a_team_removes_its_business_profile(): void
    {
        $team = Team::factory()->create();
        $profile = ClientProfile::factory()->create(['team_id' => $team->id]);

        $team->forceDelete();

        $this->assertDatabaseMissing('client_profiles', ['id' => $profile->id]);
    }

    public function test_users_default_to_the_client_role(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Client, $user->role);
        $this->assertTrue($user->isClient());
    }

    public function test_a_student_user_is_not_a_client(): void
    {
        $user = User::factory()->student()->create();

        $this->assertTrue($user->hasRole(UserRole::Student));
        $this->assertFalse($user->isClient());
    }
}
