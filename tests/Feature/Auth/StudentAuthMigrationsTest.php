<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The two schema changes behind the student sign-in overhaul.
 *
 * Both run against live data, so what they keep matters as much as what they
 * change.
 */
class StudentAuthMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sheerid_rows_and_columns_go_and_school_email_rows_stay(): void
    {
        $migration = require database_path('migrations/2026_09_17_131533_remove_sheerid_from_student_verifications_table.php');
        $migration->down();

        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();

        DB::table('student_verifications')->insert([
            [
                'user_id' => $student->id,
                'provider' => 'sheerid',
                'status' => 'pending',
                'external_id' => 'ver_123',
                'redirect_url' => 'https://services.sheerid.com/verify/prog/?verificationId=ver_123',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $other->id,
                'provider' => 'school_email',
                'status' => 'verified',
                'external_id' => null,
                'redirect_url' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migration->up();

        $this->assertFalse(Schema::hasColumn('student_verifications', 'external_id'));
        $this->assertFalse(Schema::hasColumn('student_verifications', 'redirect_url'));
        $this->assertSame(0, DB::table('student_verifications')->where('provider', 'sheerid')->count());
        $this->assertSame(1, DB::table('student_verifications')->where('user_id', $other->id)->count());

        // A row written without a provider is a school-email row now.
        DB::table('student_verifications')->insert([
            'user_id' => $student->id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('school_email', DB::table('student_verifications')->where('user_id', $student->id)->value('provider'));
    }

    public function test_the_user_columns_are_added_and_existing_google_links_keep_their_address(): void
    {
        $migration = require database_path('migrations/2026_09_17_131534_add_microsoft_id_and_google_email_to_users_table.php');
        $migration->down();

        $this->assertFalse(Schema::hasColumn('users', 'microsoft_id'));
        $this->assertFalse(Schema::hasColumn('users', 'google_email'));

        $linked = User::factory()->client()->create(['email' => 'Ada@Example.com', 'google_id' => 'google-1']);
        $unlinked = User::factory()->client()->create(['email' => 'grace@example.com']);

        $migration->up();

        $this->assertTrue(Schema::hasColumns('users', ['microsoft_id', 'google_email']));
        $this->assertSame('ada@example.com', DB::table('users')->where('id', $linked->id)->value('google_email'));
        $this->assertNull(DB::table('users')->where('id', $unlinked->id)->value('google_email'));

        // Nobody lost anything on the way.
        $this->assertSame(2, User::count());
        $this->assertSame('google-1', $linked->fresh()->google_id);
    }
}
