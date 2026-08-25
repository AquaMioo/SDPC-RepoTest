<?php

use App\Models\Course;
use App\Models\School;
use App\Models\StudentProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Education, as a list rather than a single row on the profile.
 *
 * student_profiles already carries school_id / course_id / year_level, and
 * those stay: RecruitController filters students by school and course through
 * them, so they are not decoration. What they cannot express is a second
 * entry — the senior high school before the degree — which is what this table
 * is for.
 *
 * `school` is free text because plenty of schools are not in the lookup table
 * and refusing them would be refusing the student. `school_id` and `course_id`
 * are the optional link back to the lists a client filters on, set when what
 * was typed matches something already known.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_educations', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(StudentProfile::class)->constrained()->cascadeOnDelete();

            $table->string('school');
            $table->foreignIdFor(School::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Course::class)->nullable()->constrained()->nullOnDelete();

            $table->string('area_of_study')->nullable();

            /*
             * Years, not dates. The form asks "From" and "To (or expected
             * graduation year)" because that is what anybody actually knows
             * about their own schooling; a full date would be invented
             * precision. "(expected)" is derived from to_year being in the
             * future rather than stored, so it stops being expected on its own.
             */
            $table->unsignedSmallInteger('from_year')->nullable();
            $table->unsignedSmallInteger('to_year')->nullable();

            $table->text('description')->nullable();

            $table->timestamps();

            /* Newest schooling first is the order the card reads in. */
            $table->index(['student_profile_id', 'to_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_educations');
    }
};
