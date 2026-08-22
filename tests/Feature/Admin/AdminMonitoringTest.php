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

    public function test_the_screen_lists_only_monitored_accounts(): void
    {
        $admin = User::factory()->admin()->create();

        $watched = User::factory()->create(['status' => UserStatus::Monitored]);
        User::factory()->create(['status' => UserStatus::Approved]);
        User::factory()->create(['status' => UserStatus::Pending]);
        User::factory()->create(['status' => UserStatus::Deactivated]);

        $this->actingAs($admin)
            ->get(route('admin.monitoring'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/monitoring')
                ->has('accounts', 1)
                ->where('accounts.0.id', $watched->id)
            );
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

    public function test_the_screen_is_empty_when_nobody_is_monitored(): void
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
