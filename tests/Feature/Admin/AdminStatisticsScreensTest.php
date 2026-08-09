<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\AdminStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminStatisticsScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_can_be_rendered_with_its_figures(): void
    {
        $admin = User::factory()->admin()->create();

        User::factory()->student()->approved()->count(3)->create();
        User::factory()->client()->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('stats.totalUsers', 5)
            ->where('stats.byRole.student', 3)
            ->where('stats.byRole.client', 1)
            ->where('stats.byRole.admin', 1)
            ->where('stats.byStatus.approved', 3)
            // The admin and the client are both still pending.
            ->where('stats.pendingReview', 2)
            ->where('stats.approvedPercentage', 60),
        );
    }

    public function test_the_overview_can_be_rendered_with_recent_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create([
            'name' => 'Ada Lovelace',
            'created_at' => now()->addMinute(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.overview'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/overview')
            ->where('stats.totalUsers', 2)
            ->where('recentUsers.0.name', $student->name)
            ->where('recentUsers.0.roleLabel', 'Student'),
        );
    }

    public function test_the_overview_leaves_administrators_out_of_recent_accounts(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.overview'));

        // The strip is about the people signing up, not the staff.
        $response->assertInertia(fn (Assert $page) => $page->has('recentUsers', 0));
    }

    public function test_the_overview_shows_at_most_four_recent_accounts(): void
    {
        $admin = User::factory()->admin()->create();

        User::factory()->student()->count(6)->create();

        $response = $this->actingAs($admin)->get(route('admin.overview'));

        $response->assertInertia(fn (Assert $page) => $page->has('recentUsers', 4));
    }

    public function test_the_figures_hold_up_with_no_accounts_at_all(): void
    {
        // Guards the percentage against dividing by zero on an empty database.
        $stats = app(AdminStatistics::class)->all();

        $this->assertSame(0, $stats['totalUsers']);
        $this->assertSame(0, $stats['approvedPercentage']);
        $this->assertSame(0, $stats['pendingReview']);
        $this->assertSame(0, $stats['deactivated']);
    }

    public function test_every_status_and_role_is_present_even_when_unused(): void
    {
        User::factory()->admin()->create();

        $stats = app(AdminStatistics::class)->all();

        // The screens read these keys directly, so a missing one would render
        // as blank rather than as a zero.
        $this->assertSame(
            ['pending', 'approved', 'monitored', 'deactivated'],
            array_keys($stats['byStatus']),
        );

        $this->assertSame(
            ['client', 'student', 'admin'],
            array_keys($stats['byRole']),
        );

        $this->assertSame(0, $stats['byStatus']['monitored']);
        $this->assertSame(0, $stats['byRole']['client']);
    }

    public function test_deactivated_accounts_are_counted_separately(): void
    {
        User::factory()->student()->deactivated()->count(2)->create();
        User::factory()->student()->approved()->create();

        $stats = app(AdminStatistics::class)->all();

        $this->assertSame(2, $stats['deactivated']);
        $this->assertSame(3, $stats['totalUsers']);
        $this->assertSame(33, $stats['approvedPercentage']);
    }

    public function test_non_admins_can_not_reach_the_statistics_screens(): void
    {
        foreach ([User::factory()->student(), User::factory()->client()] as $factory) {
            $user = $factory->create();

            $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.overview'))->assertForbidden();
        }
    }

    public function test_guests_can_not_reach_the_statistics_screens(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect();
        $this->get(route('admin.overview'))->assertRedirect();
    }
}
