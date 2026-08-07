<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Architecture only — no scoring runs in this module. The table exists so the
 * recruit screen can read from a stable shape once the AI module lands, and
 * so the null recommendation driver has somewhere to write.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Project::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            /** Raw model output, 0-1. */
            $table->decimal('score', 5, 4)->default(0);
            /** Presentation value shown on the match panel, 0-100. */
            $table->unsignedTinyInteger('compatibility_percentage')->default(0);
            /** Per-factor breakdown backing the "why this student" copy. */
            $table->json('reason')->nullable();

            $table->string('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();

            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index(['project_id', 'score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
