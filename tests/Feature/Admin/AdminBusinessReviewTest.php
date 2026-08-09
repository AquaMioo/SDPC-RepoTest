<?php

namespace Tests\Feature\Admin;

use App\Enums\TeamRole;
use App\Enums\VerificationStatus;
use App\Models\ClientProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminBusinessReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_queue_lists_businesses_that_submitted_a_permit(): void
    {
        $admin = User::factory()->admin()->create();

        $this->businessWithPermit('Northwind Trading', VerificationStatus::Pending);

        // No permit means nothing to review, so it never reaches the queue.
        ClientProfile::create([
            'team_id' => Team::factory()->create()->id,
            'business_name' => 'Never Submitted',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.businesses.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/businesses')
            ->has('businesses', 1)
            ->where('businesses.0.businessName', 'Northwind Trading')
            ->where('businesses.0.status', 'pending')
            ->where('businesses.0.awaitingDecision', true),
        );
    }

    public function test_businesses_awaiting_a_decision_are_listed_first(): void
    {
        $admin = User::factory()->admin()->create();

        $settled = $this->businessWithPermit('Settled Co', VerificationStatus::Verified);
        $waiting = $this->businessWithPermit('Waiting Co', VerificationStatus::Pending);

        $this->actingAs($admin)
            ->get(route('admin.businesses.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('businesses.0.id', $waiting->id)
                ->where('businesses.1.id', $settled->id),
            );
    }

    public function test_an_administrator_can_verify_a_business(): void
    {
        $admin = User::factory()->admin()->create();
        $business = $this->businessWithPermit('Northwind Trading', VerificationStatus::Pending);

        $this->actingAs($admin)
            ->from(route('admin.businesses.index'))
            ->patch(route('admin.businesses.update', $business), [
                'decision' => VerificationStatus::Verified->value,
            ])
            ->assertRedirect(route('admin.businesses.index'))
            ->assertSessionHasNoErrors();

        $business->refresh();

        $this->assertSame(VerificationStatus::Verified, $business->verification_status);
        $this->assertNotNull($business->verified_at);
        $this->assertTrue($business->isVerified());
    }

    public function test_an_administrator_can_reject_a_business(): void
    {
        $admin = User::factory()->admin()->create();
        $business = $this->businessWithPermit('Dubious Co', VerificationStatus::Pending);

        $this->actingAs($admin)
            ->from(route('admin.businesses.index'))
            ->patch(route('admin.businesses.update', $business), [
                'decision' => VerificationStatus::Rejected->value,
            ])
            ->assertSessionHasNoErrors();

        $business->refresh();

        $this->assertSame(VerificationStatus::Rejected, $business->verification_status);
        $this->assertNull($business->verified_at);
    }

    public function test_an_administrator_can_not_move_a_business_back_to_an_unsettled_state(): void
    {
        $admin = User::factory()->admin()->create();
        $business = $this->businessWithPermit('Northwind Trading', VerificationStatus::Pending);

        foreach ([VerificationStatus::Unverified, VerificationStatus::Pending] as $decision) {
            $this->actingAs($admin)
                ->from(route('admin.businesses.index'))
                ->patch(route('admin.businesses.update', $business), [
                    'decision' => $decision->value,
                ])
                ->assertSessionHasErrors('decision');
        }

        $this->assertSame(VerificationStatus::Pending, $business->refresh()->verification_status);
    }

    public function test_a_business_without_a_permit_can_not_be_decided(): void
    {
        $admin = User::factory()->admin()->create();

        $business = ClientProfile::create([
            'team_id' => Team::factory()->create()->id,
            'business_name' => 'Never Submitted',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.businesses.index'))
            ->patch(route('admin.businesses.update', $business), [
                'decision' => VerificationStatus::Verified->value,
            ])
            ->assertSessionHasErrors('decision');

        $this->assertFalse($business->refresh()->isVerified());
    }

    public function test_an_administrator_can_download_the_permit(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $business = $this->businessWithPermit('Northwind Trading', VerificationStatus::Pending);

        Storage::disk('local')->put(
            $business->permit_path,
            UploadedFile::fake()->image('permit.jpg')->getContent(),
        );

        $this->actingAs($admin)
            ->get(route('admin.businesses.permit', $business))
            ->assertOk();
    }

    public function test_a_client_can_not_download_a_permit(): void
    {
        Storage::fake('local');

        $business = $this->businessWithPermit('Northwind Trading', VerificationStatus::Pending);
        Storage::disk('local')->put($business->permit_path, 'contents');

        // Permits are private. The review route is admin only, even for the
        // business that uploaded the document.
        $this->actingAs(User::factory()->client()->create())
            ->get(route('admin.businesses.permit', $business))
            ->assertForbidden();
    }

    public function test_non_admins_can_not_reach_the_queue(): void
    {
        foreach ([User::factory()->student(), User::factory()->client()] as $factory) {
            $this->actingAs($factory->create())
                ->get(route('admin.businesses.index'))
                ->assertForbidden();
        }
    }

    public function test_guests_can_not_reach_the_queue(): void
    {
        $this->get(route('admin.businesses.index'))->assertRedirect();
    }

    /**
     * Build a business that has submitted a permit and is awaiting review.
     */
    private function businessWithPermit(string $name, VerificationStatus $status): ClientProfile
    {
        $owner = User::factory()->client()->create();
        $team = Team::factory()->create(['name' => $name]);
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $owner->switchTeam($team);

        return ClientProfile::create([
            'team_id' => $team->id,
            'business_name' => $name,
            'owner_name' => $owner->name,
            'contact_email' => $owner->email,
            'permit_path' => 'business-permits/'.$team->id.'/permit.jpg',
            'verification_status' => $status,
            'verified_at' => $status === VerificationStatus::Verified ? now() : null,
        ]);
    }
}
