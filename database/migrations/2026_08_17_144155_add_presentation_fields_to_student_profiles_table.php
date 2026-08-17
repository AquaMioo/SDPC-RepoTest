<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The fields the student profile screen renders and the table did not carry.
 *
 * Purely additive — every column is nullable and nothing existing changes
 * meaning. The profile header shows a location, the availability line shows
 * hours and a response time, and the Academic background block needs a start
 * date and a sentence that `school_id` alone cannot express.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('location')->nullable()->after('biography');

            $table->unsignedTinyInteger('weekly_hours')->nullable()->after('is_available');
            $table->string('availability_note')->nullable()->after('weekly_hours');
            $table->unsignedSmallInteger('response_time_hours')->nullable()->after('availability_note');

            $table->date('education_started_on')->nullable()->after('year_level');
            $table->text('education_note')->nullable()->after('education_started_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'location',
                'weekly_hours',
                'availability_note',
                'response_time_hours',
                'education_started_on',
                'education_note',
            ]);
        });
    }
};
