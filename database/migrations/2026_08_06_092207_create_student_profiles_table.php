<?php

use App\Models\Course;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student profiles are created here because the Client Module reads them on
 * the recruit and student-profile screens. The Student Module owns writing to
 * them; nothing in this module creates or edits a student profile.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->unique()->constrained()->cascadeOnDelete();

            $table->string('headline')->nullable();
            $table->text('biography')->nullable();
            $table->string('photo_path')->nullable();

            $table->foreignIdFor(School::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Course::class)->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('year_level')->nullable()->index();

            $table->string('github_url')->nullable();
            $table->string('portfolio_url')->nullable();

            $table->boolean('is_available')->default(true)->index();
            $table->unsignedInteger('hourly_rate')->nullable();

            /** Denormalised so the recruit grid can sort without aggregating. */
            $table->decimal('rating_average', 3, 2)->default(0)->index();
            $table->unsignedInteger('completed_projects_count')->default(0)->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
