<?php

namespace Tests\Feature\Admin;

use App\Models\AnnouncementImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Announcement is posted as images (owner, 2026-10-18): up to ten,
 * previewed on Content Management before posting, shown on both dashboards.
 */
class AnnouncementImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(AnnouncementImage::DISK);
    }

    public function test_an_admin_posts_announcement_images_in_order(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.content.images.store'), [
                'images' => [
                    UploadedFile::fake()->image('first.png', 800, 450),
                    UploadedFile::fake()->image('second.jpg', 800, 450),
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $images = AnnouncementImage::query()->orderBy('position')->get();

        $this->assertSame(['first.png', 'second.jpg'], $images->pluck('original_name')->all());
        $this->assertSame([1, 2], $images->pluck('position')->all());
        $images->each(fn (AnnouncementImage $image) => Storage::disk(AnnouncementImage::DISK)->assertExists($image->path));

        $this->actingAs($admin)
            ->get(route('admin.content.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/content')
                ->has('announcementImages', 2)
                ->where('announcementImageLimit', AnnouncementImage::MAX));
    }

    public function test_an_announcement_takes_at_most_ten_images(): void
    {
        $admin = User::factory()->admin()->create();
        AnnouncementImage::factory()->count(8)->create();

        $this->actingAs($admin)
            ->post(route('admin.content.images.store'), [
                'images' => [
                    UploadedFile::fake()->image('a.png'),
                    UploadedFile::fake()->image('b.png'),
                    UploadedFile::fake()->image('c.png'),
                ],
            ])
            ->assertSessionHasErrors('images');

        $this->assertSame(8, AnnouncementImage::query()->count());
    }

    public function test_only_images_are_accepted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.content.images.store'), [
                'images' => [UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')],
            ])
            ->assertSessionHasErrors('images.0');

        $this->assertSame(0, AnnouncementImage::query()->count());
    }

    public function test_an_admin_removes_one_image_and_its_file(): void
    {
        $admin = User::factory()->admin()->create();
        $image = AnnouncementImage::factory()->create(['path' => 'announcements/keep-out.png']);
        Storage::disk(AnnouncementImage::DISK)->put($image->path, 'png');

        $this->actingAs($admin)
            ->delete(route('admin.content.images.destroy', $image))
            ->assertRedirect();

        $this->assertModelMissing($image);
        Storage::disk(AnnouncementImage::DISK)->assertMissing('announcements/keep-out.png');
    }

    public function test_only_an_admin_posts_them(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->post(route('admin.content.images.store'), ['images' => [UploadedFile::fake()->image('a.png')]]);

        $this->assertSame(0, AnnouncementImage::query()->count());
    }

    public function test_signed_in_accounts_load_an_image_and_guests_do_not(): void
    {
        $image = AnnouncementImage::factory()->create(['path' => 'announcements/poster.png', 'original_name' => 'poster.png']);
        Storage::disk(AnnouncementImage::DISK)->put($image->path, 'png-bytes');

        $this->get(route('announcements.images.show', $image))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->student()->create())
            ->get(route('announcements.images.show', $image))
            ->assertOk();
    }
}
