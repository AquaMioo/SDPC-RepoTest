<?php

namespace Tests\Feature\Admin;

use App\Enums\CredentialStatus;
use App\Enums\UserStatus;
use App\Models\StudentCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminCredentialReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_review_screen_can_be_rendered(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create(['name' => 'Ada Lovelace']);

        $this->submissionFor($student, CredentialStatus::NeedsReview);

        $response = $this->actingAs($admin)->get(route('admin.credentials.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/credentials')
            ->where('credentials.0.student.name', 'Ada Lovelace')
            ->where('credentials.0.school', 'City College of Technology')
            ->where('credentials.0.status', 'needs_review')
            ->where('credentials.0.awaitingDecision', true),
        );
    }

    public function test_submissions_awaiting_a_decision_are_listed_first(): void
    {
        $admin = User::factory()->admin()->create();

        $settled = $this->submissionFor(
            User::factory()->student()->create(),
            CredentialStatus::Verified,
        );

        $waiting = $this->submissionFor(
            User::factory()->student()->create(),
            CredentialStatus::NeedsReview,
        );

        $response = $this->actingAs($admin)->get(route('admin.credentials.index'));

        // The queue is what the screen is for, so a settled submission never
        // sits above one still waiting, whatever order they arrived in.
        $response->assertInertia(fn (Assert $page) => $page
            ->where('credentials.0.id', $waiting->id)
            ->where('credentials.1.id', $settled->id),
        );
    }

    public function test_non_admins_can_not_reach_the_review_screen(): void
    {
        foreach ([User::factory()->student(), User::factory()->client()] as $factory) {
            $this->actingAs($factory->create())
                ->get(route('admin.credentials.index'))
                ->assertForbidden();
        }

        $this->post(route('logout'));

        $this->get(route('admin.credentials.index'))->assertRedirect();
    }

    public function test_an_administrator_can_verify_a_credential_and_approve_the_account(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $this->assertSame(UserStatus::Pending, $student->status);

        $credential = $this->submissionFor($student, CredentialStatus::NeedsReview);

        $this->actingAs($admin)
            ->from(route('admin.credentials.index'))
            ->patch(route('admin.credentials.update', $credential), [
                'decision' => CredentialStatus::Verified->value,
            ])
            ->assertRedirect(route('admin.credentials.index'))
            ->assertSessionHasNoErrors();

        $credential->refresh();

        $this->assertSame(CredentialStatus::Verified, $credential->status);
        $this->assertNotNull($credential->reviewed_at);
        $this->assertSame($admin->id, $credential->reviewed_by);

        // Verifying the document is what approves the student's account.
        $this->assertSame(UserStatus::Approved, $student->refresh()->status);
    }

    public function test_an_administrator_can_reject_a_credential_with_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $credential = $this->submissionFor($student, CredentialStatus::NeedsReview);

        $this->actingAs($admin)
            ->from(route('admin.credentials.index'))
            ->patch(route('admin.credentials.update', $credential), [
                'decision' => CredentialStatus::Rejected->value,
                'reason' => 'The document is not readable.',
            ])
            ->assertSessionHasNoErrors();

        $credential->refresh();

        $this->assertSame(CredentialStatus::Rejected, $credential->status);
        $this->assertSame('The document is not readable.', $credential->reason);

        // A rejection leaves the account alone so the student can try again.
        $this->assertSame(UserStatus::Pending, $student->refresh()->status);
        $this->assertTrue($credential->status->allowsResubmission());
    }

    public function test_a_rejection_must_carry_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $credential = $this->submissionFor(
            User::factory()->student()->create(),
            CredentialStatus::NeedsReview,
        );

        $this->actingAs($admin)
            ->from(route('admin.credentials.index'))
            ->patch(route('admin.credentials.update', $credential), [
                'decision' => CredentialStatus::Rejected->value,
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame(CredentialStatus::NeedsReview, $credential->refresh()->status);
    }

    public function test_an_administrator_can_not_move_a_credential_back_to_an_automated_state(): void
    {
        $admin = User::factory()->admin()->create();
        $credential = $this->submissionFor(
            User::factory()->student()->create(),
            CredentialStatus::NeedsReview,
        );

        $this->actingAs($admin)
            ->from(route('admin.credentials.index'))
            ->patch(route('admin.credentials.update', $credential), [
                'decision' => CredentialStatus::Pending->value,
            ])
            ->assertSessionHasErrors('decision');

        $this->assertSame(CredentialStatus::NeedsReview, $credential->refresh()->status);
    }

    public function test_a_settled_submission_can_not_be_decided_twice(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $credential = $this->submissionFor($student, CredentialStatus::Verified);

        $this->actingAs($admin)
            ->from(route('admin.credentials.index'))
            ->patch(route('admin.credentials.update', $credential), [
                'decision' => CredentialStatus::Rejected->value,
                'reason' => 'Changed my mind.',
            ])
            ->assertSessionHasErrors('decision');

        $this->assertSame(CredentialStatus::Verified, $credential->refresh()->status);
    }

    public function test_an_administrator_can_download_the_stored_document(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $credential = $this->submissionFor(
            User::factory()->student()->create(),
            CredentialStatus::NeedsReview,
        );

        Storage::disk('local')->put(
            $credential->path,
            UploadedFile::fake()->image('student-id.jpg')->getContent(),
        );

        $response = $this->actingAs($admin)
            ->get(route('admin.credentials.document', $credential));

        $response->assertOk();
        $response->assertDownload('student-id.jpg');
    }

    public function test_a_missing_document_is_not_found_rather_than_a_server_error(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $credential = $this->submissionFor(
            User::factory()->student()->create(),
            CredentialStatus::NeedsReview,
        );

        $this->actingAs($admin)
            ->get(route('admin.credentials.document', $credential))
            ->assertNotFound();
    }

    public function test_a_student_can_not_download_a_document(): void
    {
        Storage::fake('local');

        $credential = $this->submissionFor(
            User::factory()->student()->create(),
            CredentialStatus::NeedsReview,
        );

        Storage::disk('local')->put($credential->path, 'contents');

        // Documents are private: the only way in is this route, behind the
        // admin role. Not even the student who uploaded it may pull it back.
        $this->actingAs($credential->user)
            ->get(route('admin.credentials.document', $credential))
            ->assertForbidden();
    }

    /**
     * Store a submission the way StudentCredentialController does.
     */
    private function submissionFor(User $student, CredentialStatus $status): StudentCredential
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
}
