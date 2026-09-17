<?php

namespace Tests\Feature\Student;

use App\Contracts\StudentVerifier;
use App\Enums\CredentialStatus;
use App\Enums\ProjectStatus;
use App\Enums\VerificationProvider;
use App\Models\Project;
use App\Models\School;
use App\Models\StudentCredential;
use App\Models\StudentVerification;
use App\Models\User;
use App\Services\Verification\NullStudentVerifier;
use App\Services\Verification\SchoolEmailVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The automated enrolment check, and what it gates.
 *
 * With no verifier available nothing is gated at all; with one available, a
 * confirmed row is what opens the account.
 */
class StudentVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_null_verifier_is_the_shipped_default(): void
    {
        $this->assertFalse(config('verification.school_email.enabled'));
        $this->assertInstanceOf(NullStudentVerifier::class, app(StudentVerifier::class));
        $this->assertFalse(app(StudentVerifier::class)->isAvailable());
    }

    /**
     * A pass is the whole gate now, not a decoration. Nobody reviews enrolment
     * documents by hand any more, so the provider's answer is what opens an
     * account.
     */
    public function test_a_pass_opens_the_account(): void
    {
        $this->enableSchoolEmailCheck();

        $student = User::factory()->student()->create();

        // Held up while the provider has said nothing.
        $this->assertFalse($student->isVerifiedForOperating());

        StudentVerification::factory()->verified()->create([
            'user_id' => $student->id,
            'provider' => VerificationProvider::SchoolEmail,
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
     * Switch the school-email check on, with a school it can be used for.
     */
    private function enableSchoolEmailCheck(): void
    {
        config(['verification.school_email.enabled' => true]);
        School::factory()->create(['domain' => 'sti.edu.ph']);

        $this->assertInstanceOf(SchoolEmailVerifier::class, app(StudentVerifier::class));
    }
}
