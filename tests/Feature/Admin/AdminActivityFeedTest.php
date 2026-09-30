<?php

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Models\Appeal;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The administrator's bell: sign-ups, reports and feedback, with a count of
 * what arrived since it was last opened (testers, 2026-10-01).
 */
class AdminActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_bell_lists_sign_ups_reports_and_appeals(): void
    {
        $admin = User::factory()->admin()->create();
        $this->travel(1)->minutes();

        $student = User::factory()->student()->create(['name' => 'Pia Reyes']);
        Issue::factory()->create(['reporter_id' => $student->id]);
        Appeal::factory()->create([
            'user_id' => User::factory()->create(['status' => UserStatus::Monitored])->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('adminActivity.unread', fn (int $unread): bool => $unread >= 3)
                ->where('adminActivity.items', function ($items): bool {
                    $kinds = collect($items)->pluck('kind');

                    return $kinds->contains('signup')
                        && $kinds->contains('report')
                        && $kinds->contains('appeal');
                })
                ->etc());
    }

    public function test_opening_the_bell_clears_the_count(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->student()->create();

        $this->actingAs($admin)
            ->from(route('admin.dashboard'))
            ->post(route('admin.activity.seen'))
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('adminActivity.unread', 0)->etc());

        /* Something new after that counts again. */
        $this->travel(1)->minutes();
        User::factory()->client()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('adminActivity.unread', 1)->etc());
    }

    public function test_nobody_but_an_admin_is_sent_the_feed_or_may_mark_it(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('dashboard', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (Assert $page) => $page->where('adminActivity', null)->etc());

        $this->actingAs($student)
            ->post(route('admin.activity.seen'))
            ->assertForbidden();
    }
}
