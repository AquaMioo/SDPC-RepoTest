<?php

use App\Models\StudentProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The languages a student can work in.
 *
 * Free text rather than a lookup table: this city speaks Tagalog, English,
 * Kapampangan, Ilocano and a dozen others in combinations no seeded list would
 * get right, and a student who cannot find their own language on a dropdown
 * simply leaves the section empty.
 *
 * Unique per profile on the name, so the same language cannot be listed twice
 * at two different levels — which reads as a mistake rather than as a claim.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_languages', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(StudentProfile::class)->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('proficiency');

            $table->timestamps();

            $table->unique(['student_profile_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_languages');
    }
};
