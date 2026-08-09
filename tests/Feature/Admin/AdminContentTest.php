<?php

namespace Tests\Feature\Admin;

use App\Enums\SiteContentKey;
use App\Models\SiteContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_screen_renders_every_block_even_before_anything_is_saved(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.content'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/content')
            // All three keys are present as null rather than absent, so the
            // editor renders empty fields instead of undefined ones.
            ->where('content.announcements', null)
            ->where('content.rules', null)
            ->where('content.policies', null),
        );
    }

    public function test_the_screen_shows_the_saved_copy(): void
    {
        $admin = User::factory()->admin()->create();

        SiteContent::query()->create([
            'key' => SiteContentKey::Announcements,
            'body' => 'Escrow is live.',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.content'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('content.announcements', 'Escrow is live.')
            ->where('content.rules', null),
        );
    }

    public function test_an_administrator_can_save_every_block(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.content'))
            ->put(route('admin.content.update'), [
                'announcements' => 'Escrow is live.',
                'rules' => 'Scope projects in writing.',
                'policies' => 'Verify within 14 days.',
            ])
            ->assertRedirect(route('admin.content'))
            ->assertSessionHasNoErrors();

        $this->assertSame([
            'announcements' => 'Escrow is live.',
            'rules' => 'Scope projects in writing.',
            'policies' => 'Verify within 14 days.',
        ], SiteContent::allKeyed());

        $this->assertSame(3, SiteContent::count());
    }

    public function test_saving_records_who_made_the_change(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.content'))
            ->put(route('admin.content.update'), ['announcements' => 'Hello.']);

        $block = SiteContent::firstWhere('key', SiteContentKey::Announcements->value);

        $this->assertSame($admin->id, $block->updated_by);
        $this->assertTrue($block->editor->is($admin));
    }

    public function test_saving_again_updates_rather_than_duplicates(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['First.', 'Second.'] as $body) {
            $this->actingAs($admin)
                ->from(route('admin.content'))
                ->put(route('admin.content.update'), ['announcements' => $body]);
        }

        // The key is unique, so a second save edits the same row.
        $this->assertSame(1, SiteContent::where('key', SiteContentKey::Announcements->value)->count());
        $this->assertSame('Second.', SiteContent::allKeyed()['announcements']);
    }

    public function test_a_block_can_be_cleared(): void
    {
        $admin = User::factory()->admin()->create();

        SiteContent::query()->create([
            'key' => SiteContentKey::Rules,
            'body' => 'Some rules.',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.content'))
            ->put(route('admin.content.update'), ['rules' => ''])
            ->assertSessionHasNoErrors();

        // An emptied textarea is stored as null, so "never written" and
        // "deliberately cleared" read the same downstream.
        $this->assertNull(SiteContent::allKeyed()['rules']);
    }

    public function test_a_block_that_is_too_long_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.content'))
            ->put(route('admin.content.update'), [
                'announcements' => str_repeat('a', 5001),
            ])
            ->assertSessionHasErrors('announcements');

        $this->assertSame(0, SiteContent::count());
    }

    public function test_non_admins_can_not_read_or_write_the_content(): void
    {
        foreach ([User::factory()->student(), User::factory()->client()] as $factory) {
            $user = $factory->create();

            $this->actingAs($user)->get(route('admin.content'))->assertForbidden();

            $this->actingAs($user)
                ->put(route('admin.content.update'), ['announcements' => 'Mine now.'])
                ->assertForbidden();
        }

        $this->assertSame(0, SiteContent::count());
    }

    public function test_guests_can_not_read_or_write_the_content(): void
    {
        $this->get(route('admin.content'))->assertRedirect();
        $this->put(route('admin.content.update'), ['announcements' => 'Mine now.'])->assertRedirect();

        $this->assertSame(0, SiteContent::count());
    }
}
