<?php

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The single link between a student and a project. Client-initiated invites
 * and student-initiated applications share this table, separated by `source`,
 * so "who is on this project" has exactly one answer: the accepted rows.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Project::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            $table->string('status')->default(ApplicationStatus::Pending->value)->index();
            $table->string('source')->default(ApplicationSource::Applied->value);

            $table->text('cover_letter')->nullable();
            $table->unsignedInteger('proposed_rate')->nullable();

            $table->foreignIdFor(User::class, 'responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
