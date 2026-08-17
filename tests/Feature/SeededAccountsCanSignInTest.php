<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserStatus;
use App\Models\ClientProfile;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The seeded accounts are how anyone gets into a fresh clone, so a silent
 * failure here looks exactly like "the credentials are wrong".
 */
class SeededAccountsCanSignInTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_creates_every_account(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach ([
            'sdpc@admin.test',
            'student.one@sdpc.test',
            'student.two@sdpc.test',
            'client.one@sdpc.test',
            'client.two@sdpc.test',
            'pending.student@sdpc.test',
        ] as $email) {
            $this->assertNotNull(
                User::firstWhere('email', $email),
                "The seeder did not create {$email}."
            );
        }
    }

    public function test_the_seeded_passwords_actually_verify(): void
    {
        config([
            'seeding.admin_password' => 'admin-secret',
            'seeding.tester_password' => 'tester-secret',
        ]);

        $this->seed(DatabaseSeeder::class);

        $admin = User::firstWhere('email', 'sdpc@admin.test');
        $client = User::firstWhere('email', 'client.one@sdpc.test');

        // A double hash, or a plain string stored raw, would both fail here.
        $this->assertTrue(Hash::check('admin-secret', $admin->password));
        $this->assertTrue(Hash::check('tester-secret', $client->password));
    }

    public function test_the_seeded_admin_can_sign_in_through_the_admin_portal(): void
    {
        config(['seeding.admin_password' => 'admin-secret']);

        $this->seed(DatabaseSeeder::class);

        $this->post(route('admin.login.store'), [
            'email' => 'sdpc@admin.test',
            'password' => 'admin-secret',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();
    }

    public function test_the_seeded_client_can_sign_in(): void
    {
        config(['seeding.tester_password' => 'tester-secret']);

        $this->seed(DatabaseSeeder::class);

        $this->post(route('login.store'), [
            'email' => 'client.one@sdpc.test',
            'password' => 'tester-secret',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_the_seeded_client_arrives_able_to_post_work(): void
    {
        $this->seed(DatabaseSeeder::class);

        $client = User::firstWhere('email', 'client.one@sdpc.test');

        // Signing in is not the point of a tester — doing something is.
        $this->assertTrue($client->isVerifiedForOperating());

        $this->actingAs($client)
            ->post(
                route('projects.store', ['current_team' => $client->currentTeam->slug]),
                $this->projectPayload(),
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('projects', [
            'title' => 'Inventory System',
            'team_id' => $client->current_team_id,
        ]);
    }

    public function test_the_seeded_client_can_hire_the_seeded_student(): void
    {
        $this->seed(DatabaseSeeder::class);

        $client = User::firstWhere('email', 'client.one@sdpc.test');
        $student = User::firstWhere('email', 'student.one@sdpc.test');
        $team = $client->currentTeam;

        $this->actingAs($client)
            ->post(route('projects.store', ['current_team' => $team->slug]), $this->projectPayload())
            ->assertSessionHasNoErrors();

        $project = Project::firstOrFail();

        $this->actingAs($client)
            ->post(
                route('projects.invitations.store', [
                    'current_team' => $team->slug,
                    'project' => $project,
                ]),
                ['user_id' => $student->id],
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('applications', [
            'project_id' => $project->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_the_seeded_students_are_verified_and_findable(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['student.one@sdpc.test', 'student.two@sdpc.test'] as $email) {
            $student = User::firstWhere('email', $email);

            $this->assertTrue($student->isVerifiedForOperating(), "{$email} is not verified.");
            $this->assertNotNull($student->studentProfile, "{$email} has no profile to be found by.");
            $this->assertNotEmpty($student->studentProfile->skills, "{$email} lists no skills.");
        }
    }

    public function test_the_seeded_businesses_carry_their_philippine_details(): void
    {
        $this->seed(DatabaseSeeder::class);

        $profile = User::firstWhere('email', 'client.one@sdpc.test')->currentTeam->clientProfile;

        $this->assertSame('San Jose Del Monte', $profile->city);
        $this->assertSame('Bulacan', $profile->province);
        // A half-filled tester makes the profile completion meter meaningless.
        $this->assertGreaterThanOrEqual(90, $profile->completionPercentage());
    }

    public function test_the_pending_student_stays_in_the_review_queue(): void
    {
        $this->seed(DatabaseSeeder::class);

        $pending = User::firstWhere('email', 'pending.student@sdpc.test');

        // This one exists to give the admin screen something to review, so
        // verifying it along with the rest would empty that queue.
        $this->assertFalse($pending->isVerifiedForOperating());
        $this->assertSame(UserStatus::Pending, $pending->status);
    }

    public function test_reseeding_leaves_one_profile_and_one_credential_each(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $client = User::firstWhere('email', 'client.one@sdpc.test');
        $student = User::firstWhere('email', 'student.one@sdpc.test');

        $this->assertSame(1, $client->teams()->count());
        $this->assertSame(1, ClientProfile::where('team_id', $client->current_team_id)->count());
        $this->assertSame(1, $student->studentCredentials()->count());
        $this->assertTrue($client->fresh()->isVerifiedForOperating());
    }

    public function test_reseeding_updates_the_password_rather_than_failing(): void
    {
        config(['seeding.admin_password' => 'first-password']);
        $this->seed(DatabaseSeeder::class);

        // This is what a teammate does after adding the password to their .env.
        config(['seeding.admin_password' => 'second-password']);
        $this->seed(DatabaseSeeder::class);

        $admin = User::firstWhere('email', 'sdpc@admin.test');

        $this->assertSame(1, User::where('email', 'sdpc@admin.test')->count());
        $this->assertTrue(Hash::check('second-password', $admin->password));
    }

    /**
     * A posting that satisfies SaveProjectRequest.
     *
     * @return array<string, mixed>
     */
    private function projectPayload(): array
    {
        return [
            'title' => 'Inventory System',
            'description' => 'Replace the spreadsheet used across three branches.',
            'category' => 'Management / inventory system',
            'skills' => ['Laravel'],
            'status' => ProjectStatus::PendingReview->value,
        ];
    }
}
