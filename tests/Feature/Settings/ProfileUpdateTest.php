<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }

    public function test_a_profile_picture_can_be_uploaded(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => UploadedFile::fake()->image('me.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
        $this->assertStringContainsString($user->avatar_path, $user->avatarUrl());
    }

    /**
     * `avatar` is a fillable column holding the URL Google supplies, so an
     * upload reaching fill() would put an object into a string column.
     */
    public function test_uploading_a_picture_does_not_write_the_file_into_the_google_column(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['avatar' => 'https://lh3.google.test/a/photo']);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => UploadedFile::fake()->image('me.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('https://lh3.google.test/a/photo', $user->fresh()->avatar);
    }

    public function test_an_uploaded_picture_wins_over_the_google_one(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'avatar' => 'https://lh3.google.test/a/photo',
            'avatar_path' => 'avatars/1/mine.jpg',
        ]);

        $this->assertStringContainsString('avatars/1/mine.jpg', $user->avatarUrl());
    }

    public function test_an_account_with_no_picture_at_all_reports_none(): void
    {
        $user = User::factory()->create(['avatar' => null]);

        $this->assertNull($user->avatarUrl());
    }

    public function test_replacing_a_picture_removes_the_one_it_replaced(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('first.jpg'),
        ]);

        $first = $user->fresh()->avatar_path;

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('second.jpg'),
        ]);

        $second = $user->fresh()->avatar_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_something_that_is_not_an_image_is_rejected(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_the_profile_can_still_be_saved_without_a_picture(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Renamed Person',
                'email' => $user->email,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed Person', $user->fresh()->name);
        $this->assertNull($user->fresh()->avatar_path);
    }
}
