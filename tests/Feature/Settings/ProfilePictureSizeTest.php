<?php

namespace Tests\Feature\Settings;

use App\Actions\Profile\StoreProfilePicture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Profile pictures are stored at the size they are drawn.
 *
 * They were kept exactly as uploaded, so a phone photo of most of a megabyte
 * rode along on every page that shows people, through a home connection.
 */
class ProfilePictureSizeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_an_uploaded_picture_is_shrunk_to_the_drawn_size(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => UploadedFile::fake()->image('photo.jpg', 2400, 1600),
            ])
            ->assertSessionHasNoErrors();

        $path = $user->fresh()->avatar_path;

        $this->assertStringEndsWith('.webp', $path);
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(StoreProfilePicture::MAX_SIDE, $width);
        $this->assertSame(341, $height);
    }

    public function test_a_student_photo_is_shrunk_too(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->post(route('student.photo.update', ['current_team' => $student->currentTeam]), [
                'photo' => UploadedFile::fake()->image('me.png', 1200, 1200),
            ])
            ->assertSessionHasNoErrors();

        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($student->fresh()->avatar_path));
        $this->assertSame([512, 512], [$width, $height]);
    }

    public function test_a_small_picture_is_not_blown_up(): void
    {
        $small = UploadedFile::fake()->image('small.png', 120, 80);

        $webp = app(StoreProfilePicture::class)->shrink((string) file_get_contents($small->getRealPath()));

        $this->assertSame([120, 80], array_slice(getimagesizefromstring($webp), 0, 2));
    }

    public function test_something_that_is_not_an_image_is_left_alone(): void
    {
        $this->assertNull(app(StoreProfilePicture::class)->shrink('not an image'));
    }

    public function test_pictures_already_on_disk_can_be_shrunk_in_place(): void
    {
        $user = User::factory()->create();
        $old = UploadedFile::fake()->image('old.jpg', 2000, 2000)->store('avatars/'.$user->id, 'public');
        $user->forceFill(['avatar_path' => $old])->save();

        $this->artisan('avatars:shrink')->assertSuccessful();

        $new = $user->fresh()->avatar_path;
        $this->assertNotSame($old, $new);
        $this->assertFalse(Storage::disk('public')->exists($old));
        $this->assertSame(512, getimagesizefromstring(Storage::disk('public')->get($new))[0]);
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $user = User::factory()->create();
        $old = UploadedFile::fake()->image('old.jpg', 2000, 2000)->store('avatars/'.$user->id, 'public');
        $user->forceFill(['avatar_path' => $old])->save();

        $this->artisan('avatars:shrink', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame($old, $user->fresh()->avatar_path);
        $this->assertTrue(Storage::disk('public')->exists($old));
    }
}
