<?php

namespace Tests\Feature\Auth;

use App\Enums\CredentialStatus;
use App\Jobs\VerifyStudentCredential;
use App\Models\School;
use App\Models\StudentCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentCredentialTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The schools the form offers. Seeded per test: the recognised list is the
     * schools table, which is environment specific and empty by default.
     */
    private const SCHOOLS = [
        'City College of Technology',
        'Riverside Polytechnic College',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::SCHOOLS as $name) {
            School::factory()->create(['name' => $name]);
        }
    }

    public function test_the_credential_screen_can_be_rendered(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('credentials.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/credentials')
            ->where('schools', self::SCHOOLS)
            ->where('submission', null),
        );
    }

    public function test_guests_are_sent_to_the_login_screen(): void
    {
        $this->get(route('credentials.create'))->assertRedirect(route('login'));
    }

    public function test_accounts_that_do_not_need_verification_can_not_open_the_screen(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)->get(route('credentials.create'))->assertForbidden();
    }

    public function test_a_student_can_submit_a_document(): void
    {
        Storage::fake('local');
        Queue::fake();

        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->from(route('credentials.create'))
            ->post(route('credentials.store'), [
                'school' => 'City College of Technology',
                'document' => UploadedFile::fake()->image('student-id.jpg'),
            ]);

        $response->assertRedirect(route('credentials.create'));
        $response->assertSessionHasNoErrors();

        $credential = StudentCredential::firstWhere('user_id', $student->id);

        $this->assertNotNull($credential);
        $this->assertSame('City College of Technology', $credential->school);
        $this->assertSame('student-id.jpg', $credential->original_name);
        $this->assertSame(CredentialStatus::Pending, $credential->status);
        $this->assertNotSame('', $credential->checksum);

        Storage::disk('local')->assertExists($credential->path);

        Queue::assertPushed(VerifyStudentCredential::class);
    }

    public function test_the_school_must_be_one_that_exists_in_the_schools_table(): void
    {
        Storage::fake('local');
        Queue::fake();

        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->from(route('credentials.create'))
            ->post(route('credentials.store'), [
                'school' => 'Some Other School',
                'document' => UploadedFile::fake()->image('student-id.jpg'),
            ])
            ->assertSessionHasErrors('school');

        $this->assertSame(0, StudentCredential::count());
        Queue::assertNothingPushed();
    }

    public function test_the_document_must_be_an_accepted_file_type(): void
    {
        Storage::fake('local');
        Queue::fake();

        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->from(route('credentials.create'))
            ->post(route('credentials.store'), [
                'school' => 'City College of Technology',
                'document' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            ])
            ->assertSessionHasErrors('document');

        $this->assertSame(0, StudentCredential::count());
    }

    public function test_an_existing_submission_is_shown_on_the_screen(): void
    {
        $student = User::factory()->student()->create();

        $this->submissionFor($student, CredentialStatus::Pending);

        $response = $this->actingAs($student)->get(route('credentials.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/credentials')
            ->where('submission.school', 'City College of Technology')
            ->where('submission.fileName', 'student-id.jpg')
            ->where('submission.status', 'pending')
            // Pending closes the form: only a rejection reopens it.
            ->where('submission.canResubmit', false),
        );
    }

    public function test_a_rejected_submission_reopens_the_form(): void
    {
        $student = User::factory()->student()->create();

        $this->submissionFor($student, CredentialStatus::Rejected);

        $response = $this->actingAs($student)->get(route('credentials.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('submission.status', 'rejected')
            ->where('submission.canResubmit', true),
        );
    }

    public function test_a_submission_still_being_processed_can_not_be_replaced(): void
    {
        Storage::fake('local');
        Queue::fake();

        $student = User::factory()->student()->create();

        $this->submissionFor($student, CredentialStatus::Pending);

        $this->actingAs($student)
            ->from(route('credentials.create'))
            ->post(route('credentials.store'), [
                'school' => 'City College of Technology',
                'document' => UploadedFile::fake()->image('another.jpg'),
            ])
            ->assertSessionHasErrors('document');

        $this->assertSame(1, StudentCredential::count());
        Queue::assertNothingPushed();
    }

    public function test_a_rejected_submission_can_be_replaced(): void
    {
        Storage::fake('local');
        Queue::fake();

        $student = User::factory()->student()->create();

        $this->submissionFor($student, CredentialStatus::Rejected);

        $this->actingAs($student)
            ->from(route('credentials.create'))
            ->post(route('credentials.store'), [
                'school' => 'Riverside Polytechnic College',
                'document' => UploadedFile::fake()->image('another.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, StudentCredential::count());
        Queue::assertPushed(VerifyStudentCredential::class);
    }

    /**
     * Store a submission the way the controller does, without a factory.
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
            'size' => 1024,
            'checksum' => str_repeat('a', 64),
            'status' => $status,
        ])->save();

        return $credential;
    }
}
