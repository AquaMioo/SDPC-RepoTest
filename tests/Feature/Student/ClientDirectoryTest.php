<?php

namespace Tests\Feature\Student;

use App\Enums\ProjectStatus;
use App\Enums\VerificationStatus;
use App\Models\ClientProfile;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Client List — the businesses a student can look up.
 */
class ClientDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_sees_verified_businesses(): void
    {
        $student = User::factory()->student()->create();
        $verified = User::factory()->verifiedBusiness()->create();

        $this->actingAs($student)
            ->get(route('student.clients.index', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('student/clients')
                ->has('businesses.data', 1)
                ->where(
                    'businesses.data.0.businessName',
                    $verified->currentTeam->clientProfile->business_name,
                ));
    }

    public function test_an_unverified_business_is_not_listed(): void
    {
        $student = User::factory()->student()->create();

        $pending = User::factory()->client()->create();

        ClientProfile::factory()->create([
            'team_id' => $pending->current_team_id,
            'verification_status' => VerificationStatus::Pending,
        ]);

        $this->actingAs($student)
            ->get(route('student.clients.index', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('businesses.data', 0));
    }

    public function test_the_search_narrows_the_list(): void
    {
        $student = User::factory()->student()->create();

        $wanted = User::factory()->verifiedBusiness()->create();
        User::factory()->verifiedBusiness()->create();

        $wanted->currentTeam->clientProfile->update(['business_name' => 'Parrot Hardware']);

        $this->actingAs($student)
            ->get(route('student.clients.index', [
                'current_team' => $student->currentTeam,
                'search' => 'Parrot',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('businesses.data', 1)
                ->where('businesses.data.0.businessName', 'Parrot Hardware'));
    }

    public function test_a_business_profile_lists_its_open_postings(): void
    {
        $student = User::factory()->student()->create();
        $owner = User::factory()->verifiedBusiness()->create();

        $open = Project::factory()->create([
            'team_id' => $owner->current_team_id,
            'status' => ProjectStatus::Open,
        ]);

        Project::factory()->create([
            'team_id' => $owner->current_team_id,
            'status' => ProjectStatus::Draft,
        ]);

        $this->actingAs($student)
            ->get(route('student.clients.show', [
                'current_team' => $student->currentTeam,
                'business' => $owner->currentTeam,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('student/client')
                ->has('postings', 1)
                ->where('postings.0.slug', $open->slug));
    }

    public function test_the_directory_never_hands_out_contact_details(): void
    {
        $student = User::factory()->student()->create();
        $owner = User::factory()->verifiedBusiness()->create();

        $owner->currentTeam->clientProfile->update([
            'contact_email' => 'owner@example.com',
            'phone_number' => '09171234567',
        ]);

        // Messaging waits on an application linking the two sides; publishing
        // an address here would route around that for every business at once.
        $response = $this->actingAs($student)
            ->get(route('student.clients.show', [
                'current_team' => $student->currentTeam,
                'business' => $owner->currentTeam,
            ]))
            ->assertOk();

        $response->assertDontSee('owner@example.com');
        $response->assertDontSee('09171234567');
    }

    public function test_an_unverified_business_profile_is_not_found(): void
    {
        $student = User::factory()->student()->create();

        $pending = User::factory()->client()->create();

        ClientProfile::factory()->create([
            'team_id' => $pending->current_team_id,
            'verification_status' => VerificationStatus::Pending,
        ]);

        $this->actingAs($student)
            ->get(route('student.clients.show', [
                'current_team' => $student->currentTeam,
                'business' => $pending->currentTeam,
            ]))
            ->assertNotFound();
    }

    public function test_a_client_cannot_reach_the_student_client_list(): void
    {
        $client = User::factory()->verifiedBusiness()->create();

        $this->actingAs($client)
            ->get(route('student.clients.index', ['current_team' => $client->currentTeam]))
            ->assertForbidden();
    }
}
