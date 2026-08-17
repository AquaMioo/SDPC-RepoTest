<?php

namespace Tests\Feature\Client;

use App\Enums\TeamRole;
use App\Enums\VerificationStatus;
use App\Models\ClientProfile;
use App\Models\Team;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A client's own words on the landing page: written by the business, kept
 * until the business takes them down, and never written by anyone else.
 */
class TestimonialTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_verified_client_can_publish_one(): void
    {
        [$client, $team] = $this->client(VerificationStatus::Verified);

        $this->actingAs($client)
            ->from(route('client-profile.edit', ['current_team' => $team->slug]))
            ->put(route('testimonial.update', ['current_team' => $team->slug]), [
                'body' => 'A student team scoped our point-of-sale system in a week and shipped it in eight.',
                'author_title' => 'Owner',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('testimonials', [
            'team_id' => $team->id,
            'user_id' => $client->id,
            'author_title' => 'Owner',
        ]);
    }

    public function test_writing_again_replaces_what_the_business_said_before(): void
    {
        [$client, $team] = $this->client(VerificationStatus::Verified);

        Testimonial::factory()->for($team)->create(['body' => str_repeat('First take. ', 5)]);

        $this->actingAs($client)
            ->from(route('client-profile.edit', ['current_team' => $team->slug]))
            ->put(route('testimonial.update', ['current_team' => $team->slug]), [
                'body' => 'On reflection, what mattered most was seeing the build move every single day.',
                'author_title' => 'Manager',
            ])
            ->assertSessionHasNoErrors();

        // One business, one quote — not a feed.
        $this->assertSame(1, Testimonial::where('team_id', $team->id)->count());
        $this->assertStringContainsString(
            'On reflection',
            Testimonial::firstWhere('team_id', $team->id)->body,
        );
    }

    public function test_an_unverified_client_can_not_publish_one(): void
    {
        [$client, $team] = $this->client(VerificationStatus::Unverified);

        $this->actingAs($client)
            ->from(route('client-profile.edit', ['current_team' => $team->slug]))
            ->put(route('testimonial.update', ['current_team' => $team->slug]), [
                'body' => 'We are not verified but we would still like the free advertising.',
            ])
            ->assertSessionHasErrors('verification');

        $this->assertSame(0, Testimonial::count());
    }

    public function test_a_client_awaiting_review_can_not_publish_one(): void
    {
        [$client, $team] = $this->client(VerificationStatus::Pending);

        $this->actingAs($client)
            ->from(route('client-profile.edit', ['current_team' => $team->slug]))
            ->put(route('testimonial.update', ['current_team' => $team->slug]), [
                'body' => 'Submitting a permit is not the same as having it accepted.',
            ])
            ->assertSessionHasErrors('verification');

        $this->assertSame(0, Testimonial::count());
    }

    public function test_a_testimonial_has_to_actually_say_something(): void
    {
        [$client, $team] = $this->client(VerificationStatus::Verified);

        $this->actingAs($client)
            ->from(route('client-profile.edit', ['current_team' => $team->slug]))
            ->put(route('testimonial.update', ['current_team' => $team->slug]), ['body' => 'Good.'])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, Testimonial::count());
    }

    public function test_a_testimonial_can_not_run_past_the_card(): void
    {
        [$client, $team] = $this->client(VerificationStatus::Verified);

        $this->actingAs($client)
            ->from(route('client-profile.edit', ['current_team' => $team->slug]))
            ->put(route('testimonial.update', ['current_team' => $team->slug]), [
                'body' => str_repeat('a', 401),
            ])
            ->assertSessionHasErrors('body');
    }

    public function test_a_client_can_take_their_testimonial_down(): void
    {
        [$client, $team] = $this->client(VerificationStatus::Verified);

        Testimonial::factory()->for($team)->create();

        $this->actingAs($client)
            ->from(route('client-profile.edit', ['current_team' => $team->slug]))
            ->delete(route('testimonial.destroy', ['current_team' => $team->slug]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Testimonial::count());
    }

    public function test_losing_verification_does_not_trap_a_quote_on_the_homepage(): void
    {
        [$client, $team] = $this->client(VerificationStatus::Verified);

        Testimonial::factory()->for($team)->create();

        $team->clientProfile->forceFill([
            'verification_status' => VerificationStatus::Rejected,
        ])->save();

        // Retracting is never gated — otherwise a business could be held to
        // words it no longer stands behind.
        $this->actingAs($client)
            ->from(route('client-profile.edit', ['current_team' => $team->slug]))
            ->delete(route('testimonial.destroy', ['current_team' => $team->slug]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Testimonial::count());
    }

    public function test_one_business_can_not_touch_another_businesses_testimonial(): void
    {
        [, $theirTeam] = $this->client(VerificationStatus::Verified);
        [$outsider] = $this->client(VerificationStatus::Verified);

        $theirs = Testimonial::factory()->for($theirTeam)->create();

        $this->actingAs($outsider)
            ->delete(route('testimonial.destroy', ['current_team' => $theirTeam->slug]))
            ->assertForbidden();

        $this->assertModelExists($theirs);
    }

    public function test_a_guest_can_not_write_one(): void
    {
        [, $team] = $this->client(VerificationStatus::Verified);

        $this->put(route('testimonial.update', ['current_team' => $team->slug]), [
            'body' => 'Anyone at all can put words in this businesses mouth, apparently.',
        ])->assertRedirect(route('login'));

        $this->assertSame(0, Testimonial::count());
    }

    public function test_the_profile_screen_carries_the_existing_testimonial(): void
    {
        [$client, $team] = $this->client(VerificationStatus::Verified);

        Testimonial::factory()->for($team)->create([
            'body' => 'The progress board settled my biggest worry about hiring students.',
            'author_title' => 'Owner',
        ]);

        $this->actingAs($client)
            ->get(route('client-profile.edit', ['current_team' => $team->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('testimonial.body', 'The progress board settled my biggest worry about hiring students.')
                ->where('testimonial.authorTitle', 'Owner')
                ->where('canPublishTestimonial', true));
    }

    public function test_the_profile_screen_says_when_the_business_may_not_speak_yet(): void
    {
        [$client, $team] = $this->client(VerificationStatus::Pending);

        $this->actingAs($client)
            ->get(route('client-profile.edit', ['current_team' => $team->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('testimonial', null)
                ->where('canPublishTestimonial', false));
    }

    /**
     * Build a client that owns a business team in the given state.
     *
     * @return array{0: User, 1: Team}
     */
    private function client(VerificationStatus $status): array
    {
        $client = User::factory()->client()->approved()->create();

        $team = Team::factory()->create();
        $team->members()->attach($client, ['role' => TeamRole::Owner->value]);
        $client->switchTeam($team);

        ClientProfile::create([
            'team_id' => $team->id,
            'business_name' => $team->name,
            'verification_status' => $status,
            'verified_at' => $status === VerificationStatus::Verified ? now() : null,
        ]);

        return [$client->fresh(), $team->fresh()];
    }
}
