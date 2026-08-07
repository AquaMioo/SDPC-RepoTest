<?php

namespace Tests\Feature\Client;

use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Models\Application;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Skill;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_slug_is_generated_from_the_title(): void
    {
        $project = Project::factory()->create(['title' => 'Inventory System', 'slug' => null]);

        $this->assertSame('inventory-system', $project->slug);
    }

    public function test_slugs_stay_unique_across_projects_with_the_same_title(): void
    {
        Project::factory()->create(['title' => 'Inventory System', 'slug' => null]);
        $second = Project::factory()->create(['title' => 'Inventory System', 'slug' => null]);

        $this->assertSame('inventory-system-2', $second->slug);
    }

    public function test_slugs_stay_unique_against_soft_deleted_projects(): void
    {
        $first = Project::factory()->create(['title' => 'Inventory System', 'slug' => null]);
        $first->delete();

        $second = Project::factory()->create(['title' => 'Inventory System', 'slug' => null]);

        $this->assertSame('inventory-system-2', $second->slug);
    }

    public function test_members_are_the_accepted_applicants(): void
    {
        $project = Project::factory()->create();

        $accepted = User::factory()->student()->create();
        Application::factory()->accepted()->create([
            'project_id' => $project->id,
            'user_id' => $accepted->id,
        ]);

        Application::factory()->create(['project_id' => $project->id]);
        Application::factory()->rejected()->create(['project_id' => $project->id]);

        $this->assertSame(3, $project->applications()->count());
        $this->assertSame(1, $project->members()->count());
        $this->assertTrue($project->members->contains($accepted));
    }

    public function test_a_student_cannot_be_linked_to_the_same_project_twice(): void
    {
        $project = Project::factory()->create();
        $student = User::factory()->student()->create();

        Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_an_open_project_accepts_applications(): void
    {
        $project = Project::factory()->create([
            'application_deadline' => now()->addWeek(),
        ]);

        $this->assertTrue($project->isAcceptingApplications());
    }

    public function test_a_project_stops_accepting_applications_once_the_deadline_passes(): void
    {
        $project = Project::factory()->create([
            'application_deadline' => now()->subDay(),
        ]);

        $this->assertFalse($project->isAcceptingApplications());
    }

    public function test_a_project_stops_accepting_applications_when_intake_is_paused(): void
    {
        $project = Project::factory()->closedToApplications()->create();

        $this->assertFalse($project->isAcceptingApplications());
    }

    public function test_a_draft_project_does_not_accept_applications(): void
    {
        $project = Project::factory()->draft()->create();

        $this->assertFalse($project->isAcceptingApplications());
    }

    public function test_drafts_and_pending_reviews_are_hidden_from_the_public_board(): void
    {
        $open = Project::factory()->create();
        Project::factory()->draft()->create();
        Project::factory()->pendingReview()->create();

        $visible = Project::query()->publiclyVisible()->get();

        $this->assertCount(1, $visible);
        $this->assertTrue($visible->contains($open));
    }

    public function test_projects_can_be_scoped_to_the_owning_team(): void
    {
        $team = Team::factory()->create();
        $mine = Project::factory()->create(['team_id' => $team->id]);
        Project::factory()->create();

        $scoped = Project::query()->forTeam($team)->get();

        $this->assertCount(1, $scoped);
        $this->assertTrue($scoped->contains($mine));
    }

    public function test_milestones_are_returned_in_position_order(): void
    {
        $project = Project::factory()->create();

        ProjectMilestone::factory()->create([
            'project_id' => $project->id,
            'title' => 'Turnover',
            'position' => 2,
        ]);
        ProjectMilestone::factory()->create([
            'project_id' => $project->id,
            'title' => 'Design approval',
            'position' => 0,
        ]);

        $this->assertSame(
            ['Design approval', 'Turnover'],
            $project->milestones->pluck('title')->all(),
        );
    }

    public function test_skills_can_be_attached_to_a_project(): void
    {
        $project = Project::factory()->create();
        $skills = Skill::factory()->count(3)->create();

        $project->skills()->attach($skills);

        $this->assertSame(3, $project->skills()->count());
    }

    public function test_deleting_a_project_soft_deletes_it(): void
    {
        $project = Project::factory()->create();

        $project->delete();

        $this->assertSoftDeleted($project);
    }

    public function test_active_scope_covers_open_and_in_progress_projects(): void
    {
        Project::factory()->create();
        Project::factory()->inProgress()->create();
        Project::factory()->completed()->create();
        Project::factory()->archived()->create();

        $this->assertSame(2, Project::query()->active()->count());
    }

    public function test_a_status_knows_whether_the_posting_is_still_editable(): void
    {
        $this->assertTrue(ProjectStatus::Draft->isEditable());
        $this->assertTrue(ProjectStatus::Open->isEditable());
        $this->assertFalse(ProjectStatus::Completed->isEditable());
        $this->assertFalse(ProjectStatus::Archived->isEditable());
    }

    public function test_only_pending_and_shortlisted_applications_are_actionable(): void
    {
        $this->assertTrue(ApplicationStatus::Pending->isActionable());
        $this->assertTrue(ApplicationStatus::Shortlisted->isActionable());
        $this->assertFalse(ApplicationStatus::Accepted->isActionable());
        $this->assertFalse(ApplicationStatus::Withdrawn->isActionable());
    }
}
