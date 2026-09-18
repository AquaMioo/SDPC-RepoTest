<?php

namespace Tests\Feature\Console;

use App\Enums\TeamRole;
use App\Enums\VerificationProvider;
use App\Enums\VerificationStatus;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\Appeal;
use App\Models\Application;
use App\Models\ClientProfile;
use App\Models\Conversation;
use App\Models\Issue;
use App\Models\Meeting;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\Skill;
use App\Models\StudentCredential;
use App\Models\StudentEducation;
use App\Models\StudentLanguage;
use App\Models\StudentPortfolioItem;
use App\Models\StudentProfile;
use App\Models\StudentVerification;
use App\Models\TeamInvitation;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResetUserContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_postings_and_everything_attached_to_them_are_cleared(): void
    {
        $client = $this->client();
        $student = User::factory()->student()->create();
        $project = Project::factory()->create(['team_id' => $client->current_team_id, 'created_by' => $client->id]);
        $trashedProject = Project::factory()->create(['team_id' => $client->current_team_id, 'created_by' => $client->id]);
        $trashedProject->delete();

        $application = Application::factory()->accepted()->create(['project_id' => $project->id, 'user_id' => $student->id]);
        $agreement = Agreement::factory()->create([
            'project_id' => $project->id,
            'application_id' => $application->id,
            'team_id' => $client->current_team_id,
            'student_id' => $student->id,
        ]);
        AgreementMilestone::factory()->create(['agreement_id' => $agreement->id]);

        $conversation = Conversation::factory()->create(['project_id' => $project->id, 'user_id' => $student->id]);
        Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $client->id]);
        Meeting::factory()->create(['conversation_id' => $conversation->id, 'created_by' => $client->id]);

        Issue::factory()->create(['reported_user_id' => $client->id, 'reported_project_id' => $project->id]);
        Testimonial::factory()->create(['team_id' => $client->current_team_id, 'user_id' => $client->id]);
        $this->notify($student);

        $this->artisan('users:reset-content', ['--force' => true])->assertSuccessful();

        foreach ([
            'projects', 'applications', 'agreements', 'agreement_milestones', 'conversations',
            'messages', 'meetings', 'issues', 'testimonials', 'notifications',
        ] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_profiles_are_cleared_but_accounts_keep_what_sign_up_made(): void
    {
        $client = $this->client();
        $student = User::factory()->student()->create();
        $passwordHash = $student->password;

        $profile = StudentProfile::factory()->create(['user_id' => $student->id]);
        $profile->skills()->attach(Skill::factory()->create());
        StudentEducation::factory()->create(['student_profile_id' => $profile->id]);
        StudentLanguage::factory()->create(['student_profile_id' => $profile->id]);
        StudentPortfolioItem::factory()->create(['student_profile_id' => $profile->id]);

        $this->credential($student);
        $schoolEmail = StudentVerification::factory()->verified()->create(['user_id' => $student->id]);
        StudentVerification::factory()->verified()->create([
            'user_id' => $student->id,
            'provider' => VerificationProvider::Document,
        ]);

        $this->artisan('users:reset-content', ['--force' => true])->assertSuccessful();

        foreach ([
            'student_profiles', 'skill_student_profile', 'student_educations', 'student_languages',
            'student_portfolio_items', 'student_credentials',
        ] as $table) {
            $this->assertDatabaseCount($table, 0);
        }

        $this->assertModelExists($schoolEmail);
        $this->assertDatabaseMissing('student_verifications', ['provider' => VerificationProvider::Document->value]);

        $student->refresh();
        $this->assertSame($passwordHash, $student->password);

        $clientProfile = ClientProfile::where('team_id', $client->current_team_id)->sole();
        $this->assertSame('Sari-Sari Solutions', $clientProfile->business_name);
        $this->assertSame('Maria Santos', $clientProfile->owner_name);
        $this->assertSame('owner@sari-sari.test', $clientProfile->contact_email);
        $this->assertSame(VerificationStatus::Verified, $clientProfile->verification_status);
        $this->assertNotNull($clientProfile->verified_at);
        $this->assertNull($clientProfile->business_description);
        $this->assertNull($clientProfile->address);
        $this->assertNull($clientProfile->phone_number);
        $this->assertNull($clientProfile->website_url);
        $this->assertNull($clientProfile->logo_path);
    }

    public function test_students_go_back_to_a_solo_team_of_their_own(): void
    {
        $leader = User::factory()->student()->create(['name' => 'Alyssa Mendoza']);
        $leaderTeam = $leader->currentTeam;
        $leaderTeam->update(['name' => 'Code Ninjas']);

        $joiner = User::factory()->student()->create(['name' => 'Jerome Santos']);
        $joiner->currentTeam->delete();
        $joiner->teams()->detach();
        $leaderTeam->members()->attach($joiner, ['role' => TeamRole::ProjectManager->value]);
        $joiner->switchTeam($leaderTeam);

        TeamInvitation::factory()->create(['team_id' => $leaderTeam->id, 'invited_by' => $leader->id]);

        $this->artisan('users:reset-content', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('team_invitations', 0);

        $leaderTeam->refresh();
        $this->assertSame("Alyssa Mendoza's Team", $leaderTeam->name);
        $this->assertEquals([$leader->id], $leaderTeam->members()->pluck('users.id')->all());

        $joiner->refresh();
        $this->assertCount(1, $joiner->teams);
        $ownTeam = $joiner->teams->sole();
        $this->assertNotSame($leaderTeam->id, $ownTeam->id);
        $this->assertDatabaseHas('team_members', [
            'team_id' => $ownTeam->id,
            'user_id' => $joiner->id,
            'role' => TeamRole::Owner->value,
        ]);
        $this->assertSame("Jerome Santos's Team", $ownTeam->name);
        $this->assertSame($ownTeam->id, $joiner->current_team_id);
    }

    public function test_uploaded_files_are_deleted_but_an_administrators_photo_stays(): void
    {
        $admin = User::factory()->admin()->create();
        $client = $this->client();
        $project = Project::factory()->create(['team_id' => $client->current_team_id, 'created_by' => $client->id]);
        $attachment = ProjectAttachment::factory()->create(['project_id' => $project->id, 'uploaded_by' => $client->id]);
        $credential = $this->credential(User::factory()->student()->create());

        foreach ([$admin, $client] as $person) {
            $person->forceFill(['avatar_path' => 'avatars/'.$person->id.'/photo.jpg'])->save();
            Storage::disk('public')->put($person->avatar_path, 'photo');
        }

        Storage::disk('public')->put('business-logos/logo.png', 'logo');
        Storage::disk('public')->put('message-images/1/image.jpg', 'image');
        Storage::disk('public')->put('task-proofs/1/proof.pdf', 'proof');
        Storage::disk('local')->put($attachment->path, 'attachment');
        Storage::disk('local')->put($credential->path, 'credential');

        $this->artisan('users:reset-content', ['--force' => true])->assertSuccessful();

        Storage::disk('public')->assertMissing('avatars/'.$client->id.'/photo.jpg');
        Storage::disk('public')->assertMissing('business-logos/logo.png');
        Storage::disk('public')->assertMissing('message-images/1/image.jpg');
        Storage::disk('public')->assertMissing('task-proofs/1/proof.pdf');
        Storage::disk('local')->assertMissing($attachment->path);
        Storage::disk('local')->assertMissing($credential->path);
        $this->assertNull($client->refresh()->avatar_path);

        Storage::disk('public')->assertExists('avatars/'.$admin->id.'/photo.jpg');
        $this->assertSame('avatars/'.$admin->id.'/photo.jpg', $admin->refresh()->avatar_path);
    }

    public function test_moderation_records_about_people_are_kept(): void
    {
        $appeal = Appeal::factory()->create();
        $report = Issue::factory()->create();

        $this->artisan('users:reset-content', ['--force' => true])->assertSuccessful();

        $this->assertModelExists($appeal);
        $this->assertModelExists($report);
    }

    public function test_a_dry_run_only_counts(): void
    {
        $client = $this->client();
        $project = Project::factory()->create(['team_id' => $client->current_team_id, 'created_by' => $client->id]);

        $this->artisan('users:reset-content', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run: nothing was changed.')
            ->assertSuccessful();

        $this->assertModelExists($project);
        $this->assertNotNull(ClientProfile::where('team_id', $client->current_team_id)->sole()->business_description);
    }

    public function test_declining_the_confirmation_changes_nothing(): void
    {
        $client = $this->client();
        $project = Project::factory()->create(['team_id' => $client->current_team_id, 'created_by' => $client->id]);

        $this->artisan('users:reset-content')
            ->expectsConfirmation('Are you sure you want to run this command?', 'no')
            ->assertFailed();

        $this->assertModelExists($project);
    }

    /**
     * A client whose profile carries both sign up's fields and later edits.
     */
    private function client(): User
    {
        $client = User::factory()->client()->create();

        ClientProfile::factory()->verified()->create([
            'team_id' => $client->current_team_id,
            'business_name' => 'Sari-Sari Solutions',
            'owner_name' => 'Maria Santos',
            'contact_email' => 'owner@sari-sari.test',
            'logo_path' => 'business-logos/logo.png',
        ]);

        return $client;
    }

    private function credential(User $student): StudentCredential
    {
        $credential = new StudentCredential([
            'school' => 'sti.edu.ph',
            'disk' => 'local',
            'path' => 'student-credentials/'.$student->id.'/id.jpg',
            'original_name' => 'id.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'checksum' => hash('sha256', Str::random()),
        ]);

        $credential->user()->associate($student)->save();

        return $credential;
    }

    private function notify(User $user): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Example',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
