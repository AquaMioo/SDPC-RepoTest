<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The platform has no appearance preference.
 *
 * The user app is light and the admin portal is dark, both fixed by the
 * design and keyed off data-mod. A per-user light/dark toggle is not a thing
 * that should quietly reappear with the next starter-kit merge.
 */
class AppearanceRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_appearance_route_is_gone(): void
    {
        $this->assertFalse(
            Route::has('appearance.edit'),
            'The appearance settings route is back.',
        );
    }

    public function test_the_settings_screens_that_remain_still_work(): void
    {
        $user = User::factory()->student()->approved()->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }

    public function test_the_shell_never_asks_for_dark_mode(): void
    {
        $user = User::factory()->student()->approved()->create();

        $response = $this->actingAs($user)->get(route('profile.edit'));

        // The starter kit stamped `class="dark"` on <html> from a cookie and
        // ran an inline script off prefers-color-scheme. Both are gone.
        $response->assertDontSee('prefers-color-scheme', escape: false);
        $response->assertSee('data-mod="user"', escape: false);
    }

    public function test_the_admin_portal_asks_for_its_own_palette(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertSee('data-mod="admin"', escape: false);
    }
}
