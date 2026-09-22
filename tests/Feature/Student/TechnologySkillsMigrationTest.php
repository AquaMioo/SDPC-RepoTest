<?php

namespace Tests\Feature\Student;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The migration that makes a student's Skills card technologies only.
 *
 * It runs against live profiles, so what it keeps matters as much as what it
 * takes away: every real technology stays claimed, the practices and typed-in
 * extras come off, and the skills themselves survive for postings to use.
 */
class TechnologySkillsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_technologies_stay_claimed_and_everything_else_comes_off(): void
    {
        $migration = require database_path('migrations/2026_09_22_102455_add_is_technology_to_skills_table.php');
        $migration->down();
        /* The list as it stood before: nothing the migration adds. */
        DB::table('skills')->delete();

        $skills = collect([
            ['Laravel', 'laravel', 'framework'],
            ['C#', 'c', 'language'],
            ['API Integration', 'api-integration', 'general'],
            ['UI/UX Design', 'uiux-design', 'general'],
            ['Project Management', 'project-management', 'general'],
            ['Microsoft Word', 'microsoft-word', 'general'],
            /* Typed in by a student before the list had it. */
            ['Azure', 'azure', 'general'],
        ])->mapWithKeys(fn (array $skill): array => [$skill[0] => DB::table('skills')->insertGetId([
            'name' => $skill[0],
            'slug' => $skill[1],
            'type' => $skill[2],
            'created_at' => now(),
            'updated_at' => now(),
        ])]);

        $profile = StudentProfile::factory()->for(User::factory()->student())->create();

        foreach ($skills as $id) {
            DB::table('skill_student_profile')->insert([
                'student_profile_id' => $profile->id,
                'skill_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migration->up();

        $this->assertEqualsCanonicalizing(
            ['Laravel', 'C#', 'API Integration', 'UI/UX Design', 'Azure'],
            $profile->fresh()->skills->pluck('name')->all(),
        );

        /* The practices stay as skills, for postings and matching to use. */
        $this->assertFalse((bool) DB::table('skills')->where('name', 'Project Management')->value('is_technology'));
        $this->assertTrue(DB::table('skills')->where('name', 'Microsoft Word')->exists());

        /* A typed-in technology is claimed, not duplicated. */
        $this->assertSame(1, DB::table('skills')->where('slug', 'azure')->count());
        $this->assertTrue((bool) DB::table('skills')->where('slug', 'azure')->value('is_technology'));
    }

    public function test_c_and_c_plus_plus_join_the_list_without_taking_c_sharps_slug(): void
    {
        $migration = require database_path('migrations/2026_09_22_102455_add_is_technology_to_skills_table.php');
        $migration->down();
        DB::table('skills')->delete();

        DB::table('skills')->insert(['name' => 'C#', 'slug' => 'c', 'type' => 'language', 'created_at' => now(), 'updated_at' => now()]);

        $migration->up();

        $this->assertSame('C#', DB::table('skills')->where('slug', 'c')->value('name'));
        $this->assertSame('c-language', DB::table('skills')->where('name', 'C')->value('slug'));
        $this->assertSame('c-plus-plus', DB::table('skills')->where('name', 'C++')->value('slug'));
        $this->assertTrue((bool) DB::table('skills')->where('name', 'HTML')->value('is_technology'));
    }
}
