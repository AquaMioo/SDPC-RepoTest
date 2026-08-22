<?php

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Monitoring is the middle state: not approved, not shut out. This screen is
 * what stops it being a label an administrator sets once and never sees again.
 */
class AdminMonitoringTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Deactivated accounts are listed here too, which they were not before.
     * They cannot sign in to appeal from their settings, so the guest page is
     * their only door — and this is where what they write arrives. Approved
     * and pending accounts have had no decision taken against them and have
     * nothing to answer.
     */
    public function test_the_screen_lists_every_account_being_held_back(): void
    {
        $admin = User::factory()->admin()->create();

        $watched = User::factory()->create(['status' => UserStatus::Monitored]);
        $shutOut = User::factory()->create(['status' => UserStatus::Deactivated]);
        User::factory()->create(['status' => UserStatus::Approved]);
        User::factory()->create(['status' => UserStatus::Pending]);

        $this->actingAs($admin)
            ->get(route('admin.monitoring'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/monitoring')
                ->has('accounts', 2)
                ->where('accounts.0.appeal', null)
            );

        // Both are present; neither of the untouched accounts is.
        $ids = collect(json_decode(json_encode(
            $this->actingAs($admin)->get(route('admin.monitoring'))->viewData('page')['props']['accounts']
        ), true))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$watched->id, $shutOut->id], $ids);
    }

    /**
     * A monitored account with reports still open is the case an administrator
     * most needs to see, so the count travels with the row.
     */
    public function test_open_reports_are_counted_against_a_monitored_account(): void
    {
        $admin = User::factory()->admin()->create();
        $watched = User::factory()->create(['status' => UserStatus::Monitored]);

        Issue::factory()->count(2)->create(['reported_user_id' => $watched->id]);
        Issue::factory()->resolved()->create(['reported_user_id' => $watched->id]);

        $this->actingAs($admin)
            ->get(route('admin.monitoring'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('accounts.0.openReports', 2)
                ->etc()
            );
    }

    public function test_the_screen_is_empty_when_nobody_is_held_back(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->create(['status' => UserStatus::Approved]);

        $this->actingAs($admin)
            ->get(route('admin.monitoring'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('accounts', 0));
    }

    public function test_a_non_admin_cannot_reach_it(): void
    {
        $client = User::factory()->client()->approved()->create();

        $this->actingAs($client)->get(route('admin.monitoring'))->assertForbidden();
    }

    public function test_a_guest_cannot_reach_it(): void
    {
        $this->get(route('admin.monitoring'))->assertRedirect();
    }
}
