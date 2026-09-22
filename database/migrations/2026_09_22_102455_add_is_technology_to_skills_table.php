<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A student's Skills card only holds technologies.
 *
 * It used to take any text and mint it as a skill, which is how profiles came
 * to list "Microsoft Word". Now a skill says whether it is a technology, and
 * that is what a student may pick from: every language, framework and
 * database, the technical practices, and the tools that were missing from the
 * list (HTML, CSS, Azure and the like).
 *
 * The practices that are not technologies — project management, technical
 * writing, forecasting, QA, system analysis — and anything a student typed in
 * that is not on the list come off student profiles. The skills themselves
 * stay: postings and the matching vocabulary still use them.
 *
 * The lists are written out here rather than read from the seeder, so a later
 * change to the seeder cannot change what this migration did.
 */
return new class extends Migration
{
    /**
     * General skills that are technical work, by slug.
     *
     * @var list<string>
     */
    private const TECHNICAL_PRACTICES = [
        /* Str::slug drops the slash: "UI/UX Design" is uiux-design. */
        'api-integration', 'payment-integration', 'deployment-devops', 'uiux-design', 'data-analytics',
    ];

    /**
     * Technologies the list was missing, by type.
     *
     * @var array<string, list<string>>
     */
    private const ADDED = [
        'language' => ['HTML', 'CSS', 'C', 'C++', 'Ruby', 'Rust', 'R', 'SQL'],
        'framework' => ['Node.js', 'Express', 'Angular', 'Bootstrap', 'jQuery', 'Unity'],
        'database' => ['Oracle Database', 'Supabase'],
        'general' => ['Git', 'GitHub', 'Docker', 'Linux', 'AWS', 'Azure', 'Google Cloud', 'Figma', 'REST APIs'],
    ];

    /**
     * Slugs written out where Str::slug would collide.
     *
     * "C", "C++" and "C#" all slug to "c", and C# already holds it — left to
     * Str::slug, adding C would have renamed C# rather than joined it.
     *
     * @var array<string, string>
     */
    private const SLUGS = [
        'C' => 'c-language',
        'C++' => 'c-plus-plus',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->boolean('is_technology')->default(false)->after('type')->index();
        });

        DB::table('skills')
            ->whereIn('type', ['language', 'framework', 'database'])
            ->update(['is_technology' => true]);

        DB::table('skills')
            ->whereIn('slug', self::TECHNICAL_PRACTICES)
            ->update(['is_technology' => true]);

        $now = Carbon::now();

        foreach (self::ADDED as $type => $names) {
            foreach ($names as $name) {
                $slug = self::SLUGS[$name] ?? Str::slug($name);

                /* A student may already have typed it in; claim that row rather than a second one. */
                $existing = DB::table('skills')->where('name', $name)->orWhere('slug', $slug)->first();

                if ($existing !== null && ($existing->name === $name || ! isset(self::SLUGS[$name]))) {
                    DB::table('skills')->where('id', $existing->id)->update(['is_technology' => true, 'type' => $type]);

                    continue;
                }

                DB::table('skills')->insert([
                    'name' => $name,
                    'slug' => $slug,
                    'type' => $type,
                    'is_technology' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('skill_student_profile')
            ->whereIn('skill_id', DB::table('skills')->select('id')->where('is_technology', false))
            ->delete();
    }

    /**
     * Reverse the migrations.
     *
     * The flag goes; the claims taken off student profiles do not come back,
     * because nothing recorded which non-technical ones they were.
     */
    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            if (Schema::hasColumn('skills', 'is_technology')) {
                $table->dropIndex(['is_technology']);
            }
        });

        Schema::table('skills', function (Blueprint $table) {
            if (Schema::hasColumn('skills', 'is_technology')) {
                $table->dropColumn('is_technology');
            }
        });
    }
};
