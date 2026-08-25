<?php

namespace Tests\Feature\Client;

use App\Enums\Industry;
use App\Enums\OrganizationSize;
use App\Enums\TeamRole;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\ClientProfile;
use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ClientModuleTaxonomySeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
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

        // One of ten. It was one of eleven while permit_path counted; the
        // permit upload is gone from the screen, so a field nothing can fill
        // no longer holds the meter below 100.
        $this->assertSame(10, $profile->completionPercentage());
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

    public function test_a_digits_only_phone_number_is_accepted(): void
    {
        [$client, $team] = $this->verifiedClient();

        $this->actingAs($client)
            ->patch(
                route('client-profile.update', ['current_team' => $team->slug]),
                $this->profilePayload(['phone_number' => '09171234567']),
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('client_profiles', [
            'team_id' => $team->id,
            'phone_number' => '09171234567',
        ]);
    }

    /**
     * The form strips these as they are typed, so reaching the rule at all
     * means the field was posted around the form.
     */
    public function test_a_phone_number_carrying_anything_but_digits_is_rejected(): void
    {
        [$client, $team] = $this->verifiedClient();

        foreach (['+63 917 123 4567', '0917-123-4567', 'call me'] as $rejected) {
            $this->actingAs($client)
                ->patch(
                    route('client-profile.update', ['current_team' => $team->slug]),
                    $this->profilePayload(['phone_number' => $rejected]),
                )
                ->assertSessionHasErrors('phone_number');
        }

        $this->assertDatabaseMissing('client_profiles', [
            'team_id' => $team->id,
            'phone_number' => '+63 917 123 4567',
        ]);
    }

    /**
     * Profiles saved before the digits-only rule hold values like
     * "+63 917 555 0142". The edit screen strips them on load, so the owner is
     * not blocked from saving a field they never touched.
     */
    public function test_a_legacy_phone_number_is_stripped_for_the_edit_form(): void
    {
        [$client, $team] = $this->verifiedClient();

        $team->clientProfile->update(['phone_number' => '+63 917 555 0142']);

        $this->actingAs($client)
            ->get(route('client-profile.edit', ['current_team' => $team->slug]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('profile.phoneNumber', '+63 917 555 0142')
                ->etc()
            );

        // The stored value is untouched until a save; the form does the
        // stripping, so the round trip lands on digits only.
        $this->actingAs($client)
            ->patch(
                route('client-profile.update', ['current_team' => $team->slug]),
                $this->profilePayload(['phone_number' => '639175550142']),
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('client_profiles', [
            'team_id' => $team->id,
            'phone_number' => '639175550142',
        ]);
    }

    public function test_a_known_province_and_city_pair_is_accepted(): void
    {
        [$client, $team] = $this->verifiedClient();
        $this->seedLocations();

        $this->actingAs($client)
            ->patch(
                route('client-profile.update', ['current_team' => $team->slug]),
                $this->profilePayload([
                    'province' => 'Bulacan',
                    'city' => 'San Jose Del Monte',
                ]),
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('client_profiles', [
            'team_id' => $team->id,
            'province' => 'Bulacan',
            'city' => 'San Jose Del Monte',
        ]);
    }

    public function test_a_city_that_is_not_on_the_list_is_rejected(): void
    {
        [$client, $team] = $this->verifiedClient();
        $this->seedLocations();

        $this->actingAs($client)
            ->patch(
                route('client-profile.update', ['current_team' => $team->slug]),
                $this->profilePayload(['province' => 'Bulacan', 'city' => 'asd']),
            )
            ->assertSessionHasErrors('city');
    }

    /**
     * Each half is a real value on its own, so checking the columns separately
     * would wave through a place that does not exist.
     */
    public function test_a_city_from_another_province_is_rejected(): void
    {
        [$client, $team] = $this->verifiedClient();
        $this->seedLocations();

        Location::create([
            'province' => 'Cebu',
            'city' => 'Cebu City',
            'slug' => 'cebu-cebu-city',
        ]);

        $this->actingAs($client)
            ->patch(
                route('client-profile.update', ['current_team' => $team->slug]),
                $this->profilePayload([
                    'province' => 'Bulacan',
                    'city' => 'Cebu City',
                ]),
            )
            ->assertSessionHasErrors('city');
    }

    public function test_the_edit_screen_carries_the_province_and_city_options(): void
    {
        [$client, $team] = $this->verifiedClient();
        $this->seedLocations();

        $this->actingAs($client)
            ->get(route('client-profile.edit', ['current_team' => $team->slug]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('locations.0.province', 'Bulacan')
                ->has('locations.0.cities', 24)
                ->etc()
            );
    }

    public function test_the_location_may_still_be_left_empty(): void
    {
        [$client, $team] = $this->verifiedClient();
        $this->seedLocations();

        $this->actingAs($client)
            ->patch(
                route('client-profile.update', ['current_team' => $team->slug]),
                $this->profilePayload(['province' => '', 'city' => '']),
            )
            ->assertSessionHasNoErrors();
    }

    /**
     * The taxonomy seeder owns the list, so the tests read it rather than
     * inventing rows that could drift from what ships.
     */
    private function seedLocations(): void
    {
        $this->seed(ClientModuleTaxonomySeeder::class);
    }

    public function test_the_phone_number_may_still_be_left_empty(): void
    {
        [$client, $team] = $this->verifiedClient();

        $this->actingAs($client)
            ->patch(
                route('client-profile.update', ['current_team' => $team->slug]),
                $this->profilePayload(['phone_number' => '']),
            )
            ->assertSessionHasNoErrors();
    }

    /* ---------------------------------------------------------------------
     | The redesigned profile: company details, and the permit that was a trap
     * ------------------------------------------------------------------ */

    /**
     * Saving the profile must never cost a client their verification.
     *
     * It used to. Uploading a permit reset the profile to Pending, which was
     * right while administrators reviewed permits - they no longer do, and a
     * business is verified once at registration with nothing able to grant it
     * again. So the reset was a one-way door out of posting work, hiring,
     * inviting and publishing a testimonial.
     */
    public function test_saving_the_profile_never_costs_a_client_their_verification(): void
    {
        [$client, $team] = $this->verifiedClient();

        $this->actingAs($client)
            ->patch(
                route('client-profile.update', ['current_team' => $team->slug]),
                $this->profilePayload([
                    'permit' => UploadedFile::fake()->create('permit.pdf', 40, 'application/pdf'),
                ]),
            )
            ->assertSessionHasNoErrors();

        $profile = $team->clientProfile->fresh();

        $this->assertSame(VerificationStatus::Verified, $profile->verification_status);
        $this->assertNotNull($profile->verified_at);
        // And they can still do the things verification unlocks.
        $this->assertTrue($client->fresh()->isVerifiedForOperating());
    }

    public function test_a_business_records_its_industry_size_and_tagline(): void
    {
        [$client, $team] = $this->verifiedClient();

        $this->actingAs($client)
            ->patch(
                route('client-profile.update', ['current_team' => $team->slug]),
                $this->profilePayload([
                    'industry' => Industry::BusinessConsulting->value,
                    'organization_size' => OrganizationSize::Medium->value,
                    'tagline' => 'Operations systems for growing Bulacan businesses',
                ]),
            )
            ->assertSessionHasNoErrors();

        $profile = $team->clientProfile->fresh();

        $this->assertSame(Industry::BusinessConsulting, $profile->industry);
        $this->assertSame(OrganizationSize::Medium, $profile->organization_size);
        $this->assertSame('Operations systems for growing Bulacan businesses', $profile->tagline);
    }

    public function test_an_industry_that_is_not_on_the_list_is_rejected(): void
    {
        [$client, $team] = $this->verifiedClient();

        $this->actingAs($client)
            ->from(route('client-profile.edit', ['current_team' => $team->slug]))
            ->patch(
                route('client-profile.update', ['current_team' => $team->slug]),
                $this->profilePayload(['industry' => 'interdimensional_freight']),
            )
            ->assertSessionHasErrors('industry');
    }

    /**
     * The three cards the screen now draws, and the account behind them -
     * this is the only place a client can edit their own name and address.
     */
    public function test_the_edit_screen_carries_the_account_and_the_company_options(): void
    {
        [$client, $team] = $this->verifiedClient();

        $this->actingAs($client)
            ->get(route('client-profile.edit', ['current_team' => $team->slug]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('client/profile')
                ->where('account.name', $client->name)
                ->where('account.email', $client->email)
                ->where('account.roleLabel', $client->role->label())
                ->has('industries', count(Industry::cases()))
                ->has('organizationSizes', count(OrganizationSize::cases())));
    }

    /**
     * A verified client owning a team with a business profile.
     *
     * @return array{0: User, 1: Team}
     */
    private function verifiedClient(): array
    {
        $client = User::factory()->client()->approved()->create();

        $team = Team::factory()->create(['name' => 'Northwind Trading']);
        $team->members()->attach($client, ['role' => TeamRole::Owner->value]);
        $client->switchTeam($team);

        ClientProfile::create([
            'team_id' => $team->id,
            'business_name' => 'Northwind Trading',
            'verification_status' => VerificationStatus::Verified,
            'verified_at' => now(),
        ]);

        return [$client->fresh(), $team];
    }

    /**
     * A profile payload that satisfies UpdateClientProfileRequest.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function profilePayload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Northwind Trading',
        ], $overrides);
    }
}
