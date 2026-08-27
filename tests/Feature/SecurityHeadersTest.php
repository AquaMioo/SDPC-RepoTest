<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_response_carries_the_hardening_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=()');
    }

    public function test_the_camera_and_microphone_are_allowed_to_this_origin(): void
    {
        /*
         * Video meetings run here, so denying the camera to every origin
         * denies it to us. It read camera=(), microphone=() for a while, and
         * getUserMedia failed with NotAllowedError on every machine before the
         * browser even asked the person — while screen sharing carried on
         * working, because getDisplayMedia answers to display-capture instead.
         * That combination reads exactly like broken hardware, which is where
         * a day went.
         *
         * Do not narrow these back to () without also removing the meeting
         * module. `self` is the point: this origin yes, everybody else no.
         */
        $policy = $this->get(route('login'))->headers->get('Permissions-Policy');

        $this->assertStringContainsString('camera=(self)', $policy);
        $this->assertStringContainsString('microphone=(self)', $policy);

        /* Nothing here asks for location, so it stays denied to everyone. */
        $this->assertStringContainsString('geolocation=()', $policy);
    }

    public function test_hsts_is_not_sent_over_plain_http(): void
    {
        // Pinning localhost to HTTPS in a developer's browser is a hard thing
        // to undo, so the header only goes out on a secure connection.
        $this->get(route('login'))->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_sent_over_https(): void
    {
        $response = $this->get('https://localhost'.route('login', absolute: false));

        $response->assertHeader(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains',
        );
    }

    public function test_the_headers_reach_authenticated_pages_too(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['current_team' => $user->personalTeam()->slug]))
            ->assertHeader('X-Frame-Options', 'DENY');
    }
}
