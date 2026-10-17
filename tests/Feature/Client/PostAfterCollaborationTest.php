<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Agreements\Concerns\StartsCollaboration;
use Tests\TestCase;

/**
 * Once a collaboration ends the business may post again, and the dashboard's
 * "Post a Job" button has to come back with it (owner, 2026-10-18: it stayed
 * hidden after completing).
 */
class PostAfterCollaborationTest extends TestCase
{
    use RefreshDatabase, StartsCollaboration;

    public function test_post_a_job_returns_to_the_dashboard_after_the_collaboration_is_completed(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration();

        /* Not gone: disabled, naming the posting that holds the slot. */
        $this->dashboard($client)->assertInertia(fn (Assert $page) => $page
            ->where('canPostProject', false)
            ->where('postingSlot.title', $agreement->project->title)
            ->where('postingSlot.statusLabel', $agreement->project->status->label()));

        $this->actingAs($client)
            ->post(route('agreements.completion.store', ['current_team' => $client->currentTeam, 'agreement' => $agreement]), ['rating' => 5])
            ->assertSessionHasNoErrors();

        $this->dashboard($client->fresh())->assertInertia(fn (Assert $page) => $page
            ->where('canPostProject', true)
            ->where('postingSlot', null));
    }

    private function dashboard(User $client): TestResponse
    {
        return $this->actingAs($client)->get(route('client.dashboard', ['current_team' => $client->currentTeam]));
    }
}
