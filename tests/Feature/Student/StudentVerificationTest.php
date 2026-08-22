<?php

namespace Tests\Feature\Student;

use App\Contracts\StudentVerifier;
use App\Enums\CredentialStatus;
use App\Enums\ProjectStatus;
use App\Enums\VerificationProvider;
use App\Enums\VerificationStatus;
use App\Models\Project;
use App\Models\StudentCredential;
use App\Models\StudentVerification;
use App\Models\User;
use App\Services\Verification\NullStudentVerifier;
use App\Services\Verification\SheerIdStudentVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The optional third-party enrolment check.
 *
 * The point of most of these is what does NOT happen: a verified student gains
 * a badge and nothing else, and an unverified one loses nothing.
 */
class StudentVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_null_verifier_is_the_shipped_default(): void
    {
        $this->assertFalse(config('sheerid.enabled'));
        $this->assertInstanceOf(NullStudentVerifier::class, app(StudentVerifier::class));
        $this->assertFalse(app(StudentVerifier::class)->isAvailable());
    }

    public function test_the_routes_are_closed_while_it_is_switched_off(): void
    {
        $student = User::factory()->student()->approved()->create();

        $this->actingAs($student)
            ->post(route('student.verification.store'))
            ->assertNotFound();

        $this->actingAs($student)
            ->get(route('student.verification.return'))
            ->assertNotFound();
    }

    public function test_settings_does_not_advertise_it_while_it_is_switched_off(): void
    {
        $student = User::factory()->student()->approved()->create();

        $this->actingAs($student)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('studentVerification', null));
    }

    public function test_a_student_is_sent_to_the_providers_own_form(): void
    {
        $this->enableSheerId();

        Http::fake([
            '*/verification' => Http::response([
                'verificationId' => 'ver_123',
                'currentStep' => 'collectStudentPersonalInfo',
            ]),
        ]);

        $student = User::factory()->student()->approved()->create();

        $this->actingAs($student)
            ->post(route('student.verification.store'))
            ->assertRedirect();

        $verification = StudentVerification::query()
            ->where('user_id', $student->id)
            ->firstOrFail();

        $this->assertSame('ver_123', $verification->external_id);
        $this->assertSame(VerificationStatus::Pending, $verification->status);
        $this->assertStringContainsString('ver_123', $verification->redirect_url);
    }

    public function test_a_provider_that_is_down_does_not_break_the_student(): void
    {
        $this->enableSheerId();

        Http::fake(['*' => Http::response([], 503)]);

        $student = User::factory()->student()->approved()->create();

        $this->actingAs($student)
            ->post(route('student.verification.store'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, StudentVerification::query()->count());
    }

    public function test_the_answer_is_read_back_from_the_provider(): void
    {
        $this->enableSheerId();

        $student = User::factory()->student()->approved()->create();

        $verification = StudentVerification::factory()->create([
            'user_id' => $student->id,
            'provider' => VerificationProvider::SheerId,
            'status' => VerificationStatus::Pending,
            'external_id' => 'ver_123',
        ]);

        Http::fake([
            '*/verification/ver_123' => Http::response(['currentStep' => 'success']),
        ]);

        $this->actingAs($student)
            ->get(route('student.verification.return'))
            ->assertRedirect(route('profile.edit'));

        $verification->refresh();

        $this->assertSame(VerificationStatus::Verified, $verification->status);
        $this->assertNotNull($verification->verified_at);
    }

    /**
     * A pass is the whole gate now, not a decoration. Nobody reviews enrolment
     * documents by hand any more, so the provider's answer is what opens an
     * account.
     */
    public function test_a_pass_opens_the_account(): void
    {
        $this->enableSheerId();

        $student = User::factory()->student()->create();

        // Held up while the provider has said nothing.
        $this->assertFalse($student->isVerifiedForOperating());

        StudentVerification::factory()->verified()->create([
            'user_id' => $student->id,
            'provider' => VerificationProvider::SheerId,
        ]);

        $student->refresh();

        $this->assertTrue($student->isVerifiedStudent());
        $this->assertTrue($student->isVerifiedForOperating());
    }

    /**
     * With no provider configured there is nothing to check against, so a
     * student is not held up. This is the shipped default, and it is why
     * removing the manual review queue did not leave every student stranded.
     */
    public function test_a_student_can_do_everything_while_no_provider_is_configured(): void
    {
        $owner = User::factory()->verifiedBusiness()->create();

        $project = Project::factory()->create([
            'team_id' => $owner->current_team_id,
            'status' => ProjectStatus::Open,
            'applications_open' => true,
        ]);

        $student = User::factory()->student()->approved()->create();

        $student->refresh();

        $this->assertFalse(app(StudentVerifier::class)->isAvailable());
        $this->assertSame(0, $student->studentVerifications()->count());
        $this->assertTrue($student->isVerifiedForOperating());

        $this->actingAs($student)
            ->post(route('student.board.apply', [
                'current_team' => $student->currentTeam,
                'project' => $project,
            ]), ['cover_letter' => 'I have built two inventory systems for shops in Towerville.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('applications', [
            'project_id' => $project->id,
            'user_id' => $student->id,
        ]);
    }

    /**
     * Put an uploaded credential on the student's account.
     *
     * There is no factory for these — the review tests build them by hand for
     * the same reason: the row is only ever written by an upload.
     */
    private function credentialFor(User $student, CredentialStatus $status): StudentCredential
    {
        $credential = new StudentCredential;

        $credential->forceFill([
            'user_id' => $student->id,
            'school' => 'City College of Technology',
            'disk' => 'local',
            'path' => 'student-credentials/'.$student->id.'/student-id.jpg',
            'original_name' => 'student-id.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'checksum' => str_repeat('a', 64),
            'status' => $status,
        ])->save();

        return $credential;
    }

    /**
     * Switch the provider on with credentials the fake will answer for.
     */
    private function enableSheerId(): void
    {
        config([
            'sheerid.enabled' => true,
            'sheerid.program_id' => 'prog_test',
            'sheerid.access_token' => 'token_test',
        ]);

        $this->assertInstanceOf(SheerIdStudentVerifier::class, app(StudentVerifier::class));
    }
}
