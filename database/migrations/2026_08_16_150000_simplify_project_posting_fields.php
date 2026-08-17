<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Strips the posting form back: budget hiding, audience targeting and
 * milestones all go.
 *
 * Consequences worth knowing, since none of them are recoverable from the
 * schema alone:
 *
 * - Every approved posting now reaches every student. There is no invite-only
 *   or school-restricted posting any more, so the student board's visibility
 *   filtering is gone with the column.
 * - Matching loses its school, course and year factor. Scoring is now skills,
 *   scope, experience level, availability and track record.
 * - The budget is always shown.
 * - Milestones were the only dated thing on a project, so the student
 *   dashboard's calendar and its ring now work off the posting's own start and
 *   delivery dates instead.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('project_milestones');

        /*
         * The foreign keys have to go before the columns they sit on. SQLite
         * validates the whole table after a drop and refuses with "unknown
         * column in foreign key definition" otherwise, which is what happened
         * the first time this ran.
         */
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'preferred_school_id')) {
                $table->dropForeign(['preferred_school_id']);
            }

            if (Schema::hasColumn('projects', 'preferred_course_id')) {
                $table->dropForeign(['preferred_course_id']);
            }
        });

        /*
         * The index outlives its column otherwise, and SQLite then refuses the
         * drop with "error in index projects_visibility_index after drop
         * column". Guarded because a partial run may already have removed it.
         */
        if (Schema::hasColumn('projects', 'visibility')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropIndex(['visibility']);
            });
        }

        /* Guarded individually so a partial run can be finished, not restarted. */
        foreach ([
            'hide_budget',
            'visibility',
            'preferred_school_id',
            'preferred_course_id',
            'preferred_year_level',
        ] as $column) {
            if (! Schema::hasColumn('projects', $column)) {
                continue;
            }

            Schema::table('projects', function (Blueprint $table) use ($column) {
                $table->dropColumn($column);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('hide_budget')->default(false);
            $table->string('visibility')->default('all_students')->index();
            $table->foreignId('preferred_school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('preferred_course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->unsignedTinyInteger('preferred_year_level')->nullable();
        });

        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('due_date')->nullable();
            $table->unsignedInteger('amount')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }
};
