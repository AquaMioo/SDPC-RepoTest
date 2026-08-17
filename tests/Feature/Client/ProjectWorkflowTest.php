<?php

namespace Tests\Feature\Client;

use App\Enums\ProjectStatus;
use App\Enums\TeamRole;
use App\Enums\VerificationStatus;
use App\Models\ClientProfile;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $user = User::factory()->verifiedBusiness()->create();

        $this->get($this->url('projects.index', $user))
            ->assertRedirect(route('login'));
    }

    public function test_a_client_can_see_their_own_project_board(): void
    {
        $user = User::factory()->verifiedBusiness()->create();
        Project::factory()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user)
            ->get($this->url('projects.index', $user))
            ->assertOk();
    }

    public function test_a_student_cannot_reach_the_client_module(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get($this->url('projects.index', $student))
            ->assertForbidden();
    }

    public function test_a_client_cannot_see_another_teams_project(): void
    {
        $user = User::factory()->verifiedBusiness()->create();
        $foreign = Project::factory()->create();

        $this->actingAs($user)
            ->get($this->url('projects.show', $user, ['project' => $foreign]))
            ->assertForbidden();
    }

    public function test_a_client_can_publish_a_project(): void
    {
        $user = User::factory()->verifiedBusiness()->create();

        $this->actingAs($user)
            ->post($this->url('projects.store', $user), $this->validPayload())
            ->assertRedirect();

        $project = Project::query()->firstOrFail();

        $this->assertSame('Inventory System', $project->title);
        $this->assertSame($user->current_team_id, $project->team_id);
        $this->assertSame(ProjectStatus::PendingReview, $project->status);
        $this->assertNotNull($project->published_at);
    }

    public function test_publishing_attaches_skills(): void
    {
        $user = User::factory()->verifiedBusiness()->create();

        $this->actingAs($user)->post($this->url('projects.store', $user), $this->validPayload([
            'skills' => ['Laravel', 'MySQL', 'Laravel'],
        ]));

        $project = Project::query()->firstOrFail();

        $this->assertSame(2, $project->skills()->count(), 'Duplicate skill names should collapse.');
    }

    public function test_a_draft_is_not_stamped_as_published(): void
    {
        $user = User::factory()->verifiedBusiness()->create();

        $this->actingAs($user)->post($this->url('projects.store', $user), $this->validPayload([
            'status' => ProjectStatus::Draft->value,
        ]));

        $this->assertNull(Project::query()->firstOrFail()->published_at);
    }

    public function test_a_client_can_update_their_project(): void
    {
        $user = User::factory()->verifiedBusiness()->create();
        $project = Project::factory()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user)
            ->patch(
                $this->url('projects.update', $user, ['project' => $project]),
                $this->validPayload(['title' => 'Renamed']),
            )
            ->assertRedirect();

        $this->assertSame('Renamed', $project->refresh()->title);
    }

    public function test_a_completed_project_can_no_longer_be_edited(): void
    {
        $user = User::factory()->verifiedBusiness()->create();
        $project = Project::factory()->completed()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user)
            ->patch(
                $this->url('projects.update', $user, ['project' => $project]),
                $this->validPayload(),
            )
            ->assertForbidden();
    }

    public function test_a_team_member_without_permission_cannot_post_a_project(): void
    {
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        // Verification is per business, so the team they switched into needs
        // its own accepted permit. Otherwise the verification gate answers
        // first and this never reaches the permission check it is testing.
        ClientProfile::create([
            'team_id' => $team->id,
            'business_name' => $team->name,
            'permit_path' => 'business-permits/'.$team->id.'/permit.jpg',
            'verification_status' => VerificationStatus::Verified,
            'verified_at' => now(),
        ]);

        $this->actingAs($member)
            ->post($this->url('projects.store', $member), $this->validPayload())
            ->assertForbidden();
    }

    public function test_a_client_can_archive_a_project(): void
    {
        $user = User::factory()->verifiedBusiness()->create();
        $project = Project::factory()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user)
            ->patch($this->url('projects.archive', $user, ['project' => $project]))
            ->assertRedirect();

        $project->refresh();

        $this->assertSame(ProjectStatus::Archived, $project->status);
        $this->assertFalse($project->applications_open);
    }

    public function test_a_client_can_pause_and_resume_applications(): void
    {
        $user = User::factory()->verifiedBusiness()->create();
        $project = Project::factory()->create(['team_id' => $user->current_team_id]);
        $url = $this->url('projects.intake.toggle', $user, ['project' => $project]);

        $this->actingAs($user)->patch($url);
        $this->assertFalse($project->refresh()->applications_open);

        $this->actingAs($user)->patch($url);
        $this->assertTrue($project->refresh()->applications_open);
    }

    public function test_duplicating_a_project_creates_an_independent_draft(): void
    {
        $user = User::factory()->verifiedBusiness()->create();

        // Archived, because the copy is a new posting and the one-at-a-time
        // cap has to be clear before any of those can be made.
        $project = Project::factory()->archived()->create([
            'team_id' => $user->current_team_id,
            'title' => 'Inventory System',
        ]);
        $project->skills()->attach(Skill::factory()->count(2)->create());

        $this->actingAs($user)
            ->post($this->url('projects.duplicate', $user, ['project' => $project]))
            ->assertRedirect();

        $copy = Project::query()->where('id', '!=', $project->id)->firstOrFail();

        $this->assertSame('Inventory System (copy)', $copy->title);
        $this->assertNotSame($project->slug, $copy->slug);
        $this->assertSame(ProjectStatus::Draft, $copy->status);
        $this->assertNull($copy->published_at);
        $this->assertSame(2, $copy->skills()->count());
    }

    public function test_a_client_can_delete_their_project(): void
    {
        $user = User::factory()->verifiedBusiness()->create();
        $project = Project::factory()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user)
            ->delete($this->url('projects.destroy', $user, ['project' => $project]))
            ->assertRedirect($this->url('projects.index', $user));

        $this->assertSoftDeleted($project);
    }

    public function test_a_client_cannot_delete_another_teams_project(): void
    {
        $user = User::factory()->verifiedBusiness()->create();
        $foreign = Project::factory()->create();

        $this->actingAs($user)
            ->delete($this->url('projects.destroy', $user, ['project' => $foreign]))
            ->assertForbidden();

        $this->assertNotSoftDeleted($foreign);
    }

    /**
     * Build a URL with the acting user's team pinned explicitly.
     *
     * Model factories call switchTeam(), which overwrites the global
     * URL::defaults, so a bare route() here can silently point at a throwaway
     * team's slug and fail team membership instead of what is under test.
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
    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Inventory System',
            'description' => 'Replace the spreadsheet used across three branches.',
            'objectives' => "Alert staff before stock runs out\nPrint a daily summary",
            'category' => 'Management / inventory system',
            'industry' => 'Retail & grocery',
            'skills' => ['Laravel'],
            'status' => ProjectStatus::PendingReview->value,
        ], $overrides);
    }
}
