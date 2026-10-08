<?php

namespace Tests\Feature\Client;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\CredentialStatus;
use App\Enums\ProjectStatus;
use App\Models\Application;
use App\Models\Project;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Who may be matched with whom, by experience (owner, 2026-10-09).
 *
 * Client with a finished project: hires new and experienced students. New
 * client: new students only, and cannot even see the experienced ones.
 * Experienced student: applies only to clients with a finished project. New
 * student: applies anywhere. Every door is checked on the server.
 */
class HiringRuleTest extends TestCase
{
    use RefreshDatabase;

    private const REFUSAL = 'A student who has finished a project can only work with a client who has finished one too.';

    public function test_a_new_client_sees_and_invites_only_new_students(): void
    {
        $client = $this->client();
        $new = $this->student();
        $experienced = $this->student(experienced: true);
        $posting = Project::factory()->create(['team_id' => $client->current_team_id, 'status' => ProjectStatus::Open]);

        $this->actingAs($client)
            ->get(route('recruit.index', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page->loadDeferredProps('ranking', fn (AssertableInertia $reload) => $reload
                ->has('students.data', 1)
                ->where('students.data.0.id', $new->id)));

        $this->actingAs($client)
            ->get(route('students.show', ['current_team' => $client->currentTeam, 'user' => $experienced]))
            ->assertNotFound();
        $this->actingAs($client)
            ->get(route('students.show', ['current_team' => $client->currentTeam, 'user' => $new]))
            ->assertOk();

        $invite = route('projects.invitations.store', ['current_team' => $client->currentTeam, 'project' => $posting]);

        $this->actingAs($client)->post($invite, ['user_id' => $experienced->id])->assertSessionHasErrors(['user_id' => self::REFUSAL]);
        $this->actingAs($client)->post($invite, ['user_id' => $new->id])->assertSessionHasNoErrors();

        $this->assertSame([$new->id], Application::query()->pluck('user_id')->all());
    }

    public function test_a_client_with_a_finished_project_sees_and_invites_everyone(): void
    {
        $client = $this->client(experienced: true);
        $new = $this->student();
        $experienced = $this->student(experienced: true);
        $posting = Project::factory()->create(['team_id' => $client->current_team_id, 'status' => ProjectStatus::Open]);

        $this->actingAs($client)
            ->get(route('recruit.index', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page->loadDeferredProps('ranking', fn (AssertableInertia $reload) => $reload
                ->has('students.data', 2)));

        $invite = route('projects.invitations.store', ['current_team' => $client->currentTeam, 'project' => $posting]);

        $this->actingAs($client)->post($invite, ['user_id' => $experienced->id])->assertSessionHasNoErrors();
        $this->actingAs($client)->post($invite, ['user_id' => $new->id])->assertSessionHasNoErrors();

        $this->assertSame(2, Application::query()->count());
    }

    public function test_an_experienced_student_applies_only_to_clients_with_a_finished_project(): void
    {
        $student = $this->student(experienced: true);
        $newClientPosting = Project::factory()->create(['team_id' => $this->client()->current_team_id, 'status' => ProjectStatus::Open]);
        $finishedClientPosting = Project::factory()->create(['team_id' => $this->client(experienced: true)->current_team_id, 'status' => ProjectStatus::Open]);

        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page->loadDeferredProps('ranking', fn (AssertableInertia $reload) => $reload
                ->has('projects.data', 1)
                ->where('projects.data.0.slug', $finishedClientPosting->slug)));

        $this->actingAs($student)
            ->get(route('student.board.show', ['current_team' => $student->currentTeam, 'project' => $newClientPosting]))
            ->assertNotFound();
        $this->actingAs($student)
            ->post($this->applyUrl($student, $newClientPosting), $this->letter())
            ->assertNotFound();
        $this->actingAs($student)
            ->post($this->applyUrl($student, $finishedClientPosting), $this->letter())
            ->assertSessionHasNoErrors();

        $this->assertSame([$finishedClientPosting->id], Application::query()->pluck('project_id')->all());
    }

    public function test_a_new_student_applies_to_new_and_finished_clients(): void
    {
        $student = $this->student();
        $newClientPosting = Project::factory()->create(['team_id' => $this->client()->current_team_id, 'status' => ProjectStatus::Open]);
        $finishedClientPosting = Project::factory()->create(['team_id' => $this->client(experienced: true)->current_team_id, 'status' => ProjectStatus::Open]);

        $this->actingAs($student)->post($this->applyUrl($student, $newClientPosting), $this->letter())->assertSessionHasNoErrors();
        $this->actingAs($student)->post($this->applyUrl($student, $finishedClientPosting), $this->letter())->assertSessionHasNoErrors();

        $this->assertSame(2, Application::query()->count());
    }

    public function test_neither_side_can_accept_a_pairing_the_rule_forbids(): void
    {
        $client = $this->client();
        $student = $this->student(experienced: true);
        $posting = Project::factory()->create(['team_id' => $client->current_team_id, 'status' => ProjectStatus::Open]);

        /* Left over from before the student finished a project elsewhere. */
        $application = Application::factory()->create([
            'project_id' => $posting->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Pending,
            'source' => ApplicationSource::Applied,
        ]);

        $this->actingAs($client)
            ->patch(route('applications.update', ['current_team' => $client->currentTeam, 'application' => $application]), ['status' => ApplicationStatus::Accepted->value])
            ->assertSessionHasErrors(['status' => self::REFUSAL]);

        $invitation = Application::factory()->create([
            'project_id' => Project::factory()->create(['team_id' => $client->current_team_id, 'status' => ProjectStatus::Open])->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Pending,
            'source' => ApplicationSource::Invited,
        ]);

        $this->actingAs($student)
            ->post(route('student.applications.accept', ['current_team' => $student->currentTeam, 'application' => $invitation]))
            ->assertSessionHasErrors(['application' => self::REFUSAL]);

        $this->assertSame(0, Application::query()->where('status', ApplicationStatus::Accepted)->count());
    }

    /**
     * A verified business; with a Completed project when experienced.
     */
    private function client(bool $experienced = false): User
    {
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();

        if ($experienced) {
            Project::factory()->completed()->create(['team_id' => $client->current_team_id]);
        }

        return $client->fresh();
    }

    /**
     * A student cleared to apply; with a finished project when experienced.
     */
    private function student(bool $experienced = false): User
    {
        $student = User::factory()->student()->approved()->create();

        StudentProfile::factory()->for($student)->experienced($experienced ? 1 : 0)->create();

        $student->studentCredentials()->create([
            'school' => 'City College of Technology',
            'disk' => 'local',
            'path' => 'credentials/'.$student->id.'.pdf',
            'original_name' => 'registration.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'checksum' => hash('sha256', (string) $student->id),
        ])->forceFill(['status' => CredentialStatus::Verified])->save();

        return $student->fresh();
    }

    private function applyUrl(User $student, Project $project): string
    {
        return route('student.board.apply', ['current_team' => $student->currentTeam, 'project' => $project]);
    }

    /**
     * @return array<string, string>
     */
    private function letter(): array
    {
        return ['cover_letter' => 'I have built two inventory systems before this one.'];
    }
}
