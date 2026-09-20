<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\TeamRole;
use App\Models\Application;
use App\Models\Project;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * One picture per account, everywhere it is drawn.
 *
 * users.avatar holds whatever the OAuth provider last handed over and
 * users.avatar_path holds an upload; User::avatarUrl() is the only thing that
 * knows an upload wins. Any screen that reads a column directly shows a stale
 * face, which is exactly what several of them were doing — so each one here
 * gives the account both a Google URL and an upload and insists on the upload.
 */
class AvatarEverywhereTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_team_roster_draws_an_uploaded_picture_over_the_google_one(): void
    {
        Storage::fake('public');

        $lead = User::factory()->student()->approved()->create();
        $team = $lead->currentTeam;

        $teammate = $this->withBothPictures(
            User::factory()->student()->approved()->create(),
        );

        $team->members()->attach($teammate, ['role' => TeamRole::Member]);

        $this->actingAs($lead)
            ->get(route('teams.edit', ['team' => $team->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where(
                    'members.1.avatarUrl',
                    $this->uploadedUrl($teammate),
                )
            );
    }

    public function test_the_recruit_grid_draws_an_uploaded_picture_over_the_google_one(): void
    {
        Storage::fake('public');

        $client = User::factory()->client()->approved()->verifiedBusiness()->create();

        $student = $this->withBothPictures(
            User::factory()->student()->approved()->create(),
        );

        StudentProfile::factory()->create(['user_id' => $student->id]);

        $this->actingAs($client)
            ->get(route('recruit.index', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('students.data.0.avatarUrl', $this->uploadedUrl($student))
            );
    }

    public function test_the_applicant_list_draws_an_uploaded_picture_over_the_google_one(): void
    {
        Storage::fake('public');

        $client = User::factory()->client()->approved()->verifiedBusiness()->create();

        $student = $this->withBothPictures(
            User::factory()->student()->approved()->create(),
        );

        $project = Project::factory()->create(['team_id' => $client->current_team_id]);

        Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Pending,
        ]);

        $this->actingAs($client)
            ->get(route('projects.applicants.index', [
                'current_team' => $client->currentTeam,
                'project' => $project,
            ]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where(
                    'applications.0.student.avatarUrl',
                    $this->uploadedUrl($student),
                )
            );
    }

    /**
     * The header carries it on every screen, so one assertion covers the lot.
     */
    public function test_the_shared_auth_prop_draws_an_uploaded_picture_over_the_google_one(): void
    {
        Storage::fake('public');

        $student = $this->withBothPictures(
            User::factory()->student()->approved()->create(),
        );

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.avatarUrl', $this->uploadedUrl($student))
            );
    }

    /**
     * An account that has uploaded nothing still shows the provider's picture
     * rather than falling to initials.
     */
    public function test_a_google_picture_still_shows_when_nothing_was_uploaded(): void
    {
        $student = User::factory()->student()->approved()->create();

        $student->forceFill([
            'avatar' => 'https://lh3.googleusercontent.com/only-google',
            'avatar_path' => null,
        ])->save();

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where(
                    'auth.avatarUrl',
                    'https://lh3.googleusercontent.com/only-google',
                )
            );
    }

    /**
     * Both kinds of picture at once, which is the case that catches a screen
     * reading the column instead of calling avatarUrl().
     */
    private function withBothPictures(User $user): User
    {
        $user->forceFill([
            'avatar' => 'https://lh3.googleusercontent.com/stale-google-photo',
            'avatar_path' => UploadedFile::fake()
                ->image('me.jpg')
                ->store('avatars/'.$user->id, 'public'),
        ])->save();

        return $user->fresh();
    }

    private function uploadedUrl(User $user): string
    {
        return Storage::disk('public')->url($user->fresh()->avatar_path);
    }
}
