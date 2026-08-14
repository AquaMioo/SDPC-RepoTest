<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The seeded accounts are how anyone gets into a fresh clone, so a silent
 * failure here looks exactly like "the credentials are wrong".
 */
class SeededAccountsCanSignInTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_creates_every_account(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach ([
            'sdpc@admin.test',
            'student.one@sdpc.test',
            'student.two@sdpc.test',
            'client.one@sdpc.test',
            'client.two@sdpc.test',
            'pending.student@sdpc.test',
        ] as $email) {
            $this->assertNotNull(
                User::firstWhere('email', $email),
                "The seeder did not create {$email}."
            );
        }
    }

    public function test_the_seeded_passwords_actually_verify(): void
    {
        config([
            'seeding.admin_password' => 'admin-secret',
            'seeding.tester_password' => 'tester-secret',
        ]);

        $this->seed(DatabaseSeeder::class);

        $admin = User::firstWhere('email', 'sdpc@admin.test');
        $client = User::firstWhere('email', 'client.one@sdpc.test');

        // A double hash, or a plain string stored raw, would both fail here.
        $this->assertTrue(Hash::check('admin-secret', $admin->password));
        $this->assertTrue(Hash::check('tester-secret', $client->password));
    }

    public function test_the_seeded_admin_can_sign_in_through_the_admin_portal(): void
    {
        config(['seeding.admin_password' => 'admin-secret']);

        $this->seed(DatabaseSeeder::class);

        $this->post(route('admin.login.store'), [
            'email' => 'sdpc@admin.test',
            'password' => 'admin-secret',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();
    }

    public function test_the_seeded_client_can_sign_in(): void
    {
        config(['seeding.tester_password' => 'tester-secret']);

        $this->seed(DatabaseSeeder::class);

        $this->post(route('login.store'), [
            'email' => 'client.one@sdpc.test',
            'password' => 'tester-secret',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_reseeding_updates_the_password_rather_than_failing(): void
    {
        config(['seeding.admin_password' => 'first-password']);
        $this->seed(DatabaseSeeder::class);

        // This is what a teammate does after adding the password to their .env.
        config(['seeding.admin_password' => 'second-password']);
        $this->seed(DatabaseSeeder::class);

        $admin = User::firstWhere('email', 'sdpc@admin.test');

        $this->assertSame(1, User::where('email', 'sdpc@admin.test')->count());
        $this->assertTrue(Hash::check('second-password', $admin->password));
    }
}
