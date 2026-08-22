<?php

namespace Tests\Feature\Client;

use App\Enums\TeamRole;
use App\Enums\VerificationStatus;
use App\Models\ClientProfile;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registering gets a business in and lets it look around. Posting work and
 * hiring wait until an administrator has accepted its permit.
 */
class ClientVerificationGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unverified_client_can_still_look_around(): void
    {
        [$client, $team] = $this->client(VerificationStatus::Unverified);

        foreach (['projects.index', 'recruit.index', 'client-profile.edit'] as $route) {
            $this->actingAs($client)
                ->get(route($route, ['current_team' => $team->slug]))
                ->assertOk();
        }
    }

    public function test_an_unverified_client_can_reach_the_profile_that_lets_them_submit(): void
    {
        [$client, $team] = $this->client(VerificationStatus::Unverified);

        // The way out of the gate must never be behind the gate.
        $this->actingAs($client)
            ->get(route('client-profile.edit', ['current_team' => $team->slug]))
            ->assertOk();
    }

    public function test_an_unverified_client_can_not_open_the_new_project_form(): void
    {
        [$client, $team] = $this->client(VerificationStatus::Unverified);

        $this->actingAs($client)
            ->from(route('projects.index', ['current_team' => $team->slug]))
            ->get(route('projects.create', ['current_team' => $team->slug]))
            ->assertRedirect()
            ->assertSessionHasErrors('verification');
    }

    public function test_an_unverified_client_can_not_post_work(): void
    {
        [$client, $team] = $this->client(VerificationStatus::Unverified);

        $this->actingAs($client)
            ->from(route('projects.index', ['current_team' => $team->slug]))
            ->post(route('projects.store', ['current_team' => $team->slug]), [
                'title' => 'Build me a website',
            ])
            ->assertSessionHasErrors('verification');

        $this->assertSame(0, Project::count());
    }

    public function test_a_client_awaiting_review_still_can_not_post_work(): void
    {
        // Submitting the permit is not the same as having it accepted.
        [$client, $team] = $this->client(VerificationStatus::Pending);

        $this->actingAs($client)
            ->from(route('projects.index', ['current_team' => $team->slug]))
            ->post(route('projects.store', ['current_team' => $team->slug]), [
                'title' => 'Build me a website',
            ])
            ->assertSessionHasErrors('verification');

        $this->assertSame(0, Project::count());
    }

    public function test_a_rejected_client_can_not_post_work(): void
    {
        [$client, $team] = $this->client(VerificationStatus::Rejected);

        $this->actingAs($client)
            ->from(route('projects.index', ['current_team' => $team->slug]))
            ->get(route('projects.create', ['current_team' => $team->slug]))
            ->assertSessionHasErrors('verification');
    }

    public function test_a_verified_client_may_open_the_new_project_form(): void
    {
        [$client, $team] = $this->client(VerificationStatus::Verified);

        $this->actingAs($client)
            ->get(route('projects.create', ['current_team' => $team->slug]))
            ->assertOk();
    }

    public function test_verification_is_what_flips_the_gate(): void
    {
        [$client, $team] = $this->client(VerificationStatus::Pending);

        $this->assertFalse($client->isVerifiedForOperating());

        $client->currentTeam->clientProfile->forceFill([
            'verification_status' => VerificationStatus::Verified,
            'verified_at' => now(),
        ])->save();

        $this->assertTrue($client->fresh()->isVerifiedForOperating());
    }

    public function test_an_administrator_is_never_held_up_by_the_gate(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->isVerifiedForOperating());
    }

    /**
     * A student is answerable to the verification provider, not to this
     * business profile. With no provider configured there is nothing to check
     * against, so the gate stays open rather than closing forever — see
     * User::hasPassedStudentVerification.
     */
    public function test_a_student_is_gated_on_the_provider_not_the_business_profile(): void
    {
        $student = User::factory()->student()->create();

        $this->assertTrue($student->isVerifiedForOperating());
    }

    /**
     * Build a client that owns a business team in the given state.
     *
     * @return array{0: User, 1: Team}
     */
    private function client(VerificationStatus $status): array
    {
        $client = User::factory()->client()->approved()->create();

        $team = Team::factory()->create(['name' => 'Northwind Trading']);
        $team->members()->attach($client, ['role' => TeamRole::Owner->value]);
        $client->switchTeam($team);

        ClientProfile::create([
            'team_id' => $team->id,
            'business_name' => 'Northwind Trading',
            'permit_path' => $status === VerificationStatus::Unverified
                ? null
                : 'business-permits/'.$team->id.'/permit.jpg',
            'verification_status' => $status,
            'verified_at' => $status === VerificationStatus::Verified ? now() : null,
        ]);

        return [$client->fresh(), $team];
    }
}
