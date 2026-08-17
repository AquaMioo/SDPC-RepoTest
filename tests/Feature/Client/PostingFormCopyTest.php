<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The posting form promises the client what happens when they publish.
 *
 * That promise used to be hardcoded copy saying an administrator screens every
 * posting — which was simply false whenever projects.auto_approve was on, and
 * the client would watch their brief appear on the student board immediately
 * after being told it would be reviewed first.
 */
class PostingFormCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_form_says_a_posting_is_reviewed_when_review_is_on(): void
    {
        config(['projects.auto_approve' => false]);

        $client = User::factory()->client()->approved()->verifiedBusiness()->create();

        $this->actingAs($client)
            ->get(route('projects.create', ['current_team' => $client->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('reviewedBeforeGoingLive', true));
    }

    public function test_the_form_admits_a_posting_goes_live_immediately(): void
    {
        config(['projects.auto_approve' => true]);

        $client = User::factory()->client()->approved()->verifiedBusiness()->create();

        $this->actingAs($client)
            ->get(route('projects.create', ['current_team' => $client->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('reviewedBeforeGoingLive', false));
    }
}
