<?php

use App\Models\StudentProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student Background History — the academic portfolio from the vision document.
 *
 * Capstone outputs, prototypes, coursework and client work a student has
 * already shipped. This is the evidence a client reads when deciding whether a
 * student can do the job, and until now the platform had nowhere to keep it:
 * `student_profiles` carried a single `portfolio_url` and nothing else.
 *
 * Skills attach through a pivot rather than a free-text tag list so the
 * matching engine can read a student's demonstrated skills, not just their
 * claimed ones.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_portfolio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(StudentProfile::class)->constrained()->cascadeOnDelete();

            $table->string('title');
            /* What the student did on it: "Lead developer", "Front-end". */
            $table->string('role')->nullable();
            $table->text('description')->nullable();

            $table->unsignedSmallInteger('year')->nullable();
            $table->string('url')->nullable();
            $table->string('repository_url')->nullable();

            $table->boolean('is_featured')->default(true);
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->index(['student_profile_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_portfolio_items');
    }
};
