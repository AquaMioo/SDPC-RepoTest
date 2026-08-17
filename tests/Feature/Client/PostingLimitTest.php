<?php

namespace Tests\Feature\Client;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A client team runs one project at a time.
 *
 * The slot is held by any posting that is not finished, and comes back once
 * that posting is completed, closed or archived. Every route that can mint a
 * posting — the form, the store, the duplicate — answers to the same rule.
 */
class PostingLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_team_with_no_postings_can_post(): void
    {
        $user = User::factory()->verifiedBusiness()->create();

        $this->actingAs($user)
            ->post($this->url('projects.store', $user), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Project::query()->count());
    }

    #[DataProvider('unfinishedStateProvider')]
    public function test_an_unfinished_posting_blocks_a_second_one(string $state): void
    {
        $user = User::factory()->verifiedBusiness()->create();
        Project::factory()->{$state}()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user)
            ->post($this->url('projects.store', $user), $this->payload())
            ->assertForbidden();

        $this->assertSame(1, Project::query()->count());
    }

    public function test_the_posting_form_is_closed_while_the_slot_is_taken(): void
    {
        $user = User::factory()->verifiedBusiness()->create();
        Project::factory()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user)
            ->get($this->url('projects.create', $user))
            ->assertForbidden();
    }

    #[DataProvider('finishedStateProvider')]
    public function test_a_finished_posting_frees_the_slot(string $state): void
    {
        $user = User::factory()->verifiedBusiness()->create();
        Project::factory()->{$state}()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user)
            ->post($this->url('projects.store', $user), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Project::query()->count());
    }

    public function test_archiving_the_current_posting_lets_the_client_post_again(): void
    {
        $user = User::factory()->verifiedBusiness()->create();
        $project = Project::factory()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user)
            ->get($this->url('projects.create', $user))
            ->assertForbidden();

        $this->actingAs($user)
            ->patch($this->url('projects.archive', $user, ['project' => $project]));

        $this->actingAs($user)
            ->get($this->url('projects.create', $user))
            ->assertOk();
    }

    public function test_duplicating_is_refused_while_the_slot_is_taken(): void
    {
        $user = User::factory()->verifiedBusiness()->create();
        $project = Project::factory()->create(['team_id' => $user->current_team_id]);

        // Otherwise the copy button is a one-click way around the cap.
        $this->actingAs($user)
            ->post($this->url('projects.duplicate', $user, ['project' => $project]))
            ->assertForbidden();

        $this->assertSame(1, Project::query()->count());
    }

    public function test_another_teams_posting_does_not_hold_your_slot(): void
    {
        $user = User::factory()->verifiedBusiness()->create();
        Project::factory()->create();

        $this->actingAs($user)
            ->post($this->url('projects.store', $user), $this->payload())
            ->assertSessionHasNoErrors();
    }

    public function test_the_client_can_still_edit_the_posting_holding_the_slot(): void
    {
        $user = User::factory()->verifiedBusiness()->create();
        $project = Project::factory()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user)
            ->patch(
                $this->url('projects.update', $user, ['project' => $project]),
                $this->payload(['title' => 'Renamed']),
            )
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $project->refresh()->title);
    }

    public function test_the_board_hides_the_post_button_while_the_slot_is_taken(): void
    {
        $user = User::factory()->verifiedBusiness()->create();
        Project::factory()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user)
            ->get($this->url('projects.index', $user))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canCreate', false));
    }

    public function test_the_board_offers_the_post_button_when_the_slot_is_free(): void
    {
        $user = User::factory()->verifiedBusiness()->create();
        Project::factory()->completed()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user)
            ->get($this->url('projects.index', $user))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canCreate', true));
    }

    public function test_the_dashboard_hides_the_post_button_while_the_slot_is_taken(): void
    {
        $user = User::factory()->verifiedBusiness()->create();
        Project::factory()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user)
            ->get(route('client.dashboard', ['current_team' => $user->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canPostProject', false));
    }

    /**
     * The factory states whose postings hold the team's slot.
     *
     * @return array<string, array{string}>
     */
    public static function unfinishedStateProvider(): array
    {
        return [
            'draft' => ['draft'],
            'pending review' => ['pendingReview'],
            'in progress' => ['inProgress'],
        ];
    }

    /**
     * The factory states whose postings give the slot back.
     *
     * @return array<string, array{string}>
     */
    public static function finishedStateProvider(): array
    {
        return [
            'completed' => ['completed'],
            'archived' => ['archived'],
        ];
    }

    /**
     * Build a URL with the acting user's team pinned explicitly.
     *
     * Model factories call switchTeam(), which overwrites the global
     * URL::defaults, so a bare route() here can point at a throwaway team.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function url(string $name, User $user, array $parameters = []): string
    {
        return route($name, [
            'current_team' => $user->currentTeam,
            ...$parameters,
        ]);
    }

    /**
     * Build a valid posting payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return [
            'title' => 'Inventory System',
            'description' => 'Replace the spreadsheet used across three branches.',
            'category' => 'Management / inventory system',
            'skills' => ['Laravel'],
            'status' => ProjectStatus::PendingReview->value,
            ...$overrides,
        ];
    }
}
