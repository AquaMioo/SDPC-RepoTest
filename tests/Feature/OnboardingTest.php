<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The first-login walkthrough (owner, 2026-10-18) shows until it is finished
 * or skipped, and then never again for that account.
 */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_account_is_offered_the_walkthrough(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get(route('client.dashboard', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.has_completed_onboarding', false));
    }

    public function test_finishing_or_skipping_it_puts_it_away_for_good(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->from(route('dashboard', ['current_team' => $student->currentTeam]))
            ->post(route('onboarding.complete'))
            ->assertRedirect(route('dashboard', ['current_team' => $student->currentTeam]));

        $this->assertTrue($student->refresh()->has_completed_onboarding);

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.has_completed_onboarding', true));

        /* Asking again changes nothing. */
        $this->actingAs($student)->post(route('onboarding.complete'))->assertRedirect();
        $this->assertTrue($student->refresh()->has_completed_onboarding);
    }

    public function test_a_guest_cannot_mark_it(): void
    {
        $this->post(route('onboarding.complete'))->assertRedirect(route('login'));
    }
}
