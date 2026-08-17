<?php

use App\Enums\ProjectStatus;
use App\Models\Course;
use App\Models\School;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Team::class)->constrained()->cascadeOnDelete();
            /** Retained for the project history panel even if the poster leaves the team. */
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->text('objectives')->nullable();
            $table->string('category')->index();
            $table->string('industry')->nullable()->index();

            /* Literals: these columns and their enums are dropped later. */
            $table->string('budget_type')->default('fixed');
            $table->unsignedInteger('budget_amount')->nullable();
            $table->boolean('hide_budget')->default(false);

            $table->date('start_date')->nullable();
            $table->date('target_delivery_date')->nullable();
            $table->date('application_deadline')->nullable()->index();
            $table->date('expected_completion_date')->nullable();
            $table->string('weekly_commitment')->nullable();

            $table->unsignedTinyInteger('team_size')->default(1);
            $table->string('experience_level')->default('any');
            $table->boolean('open_to_capstone_groups')->default(true);

            /* Dropped later by simplify_project_posting_fields; the literal keeps
               this migration runnable after the enum was deleted. */
            $table->string('visibility')->default('all_students')->index();
            $table->foreignIdFor(School::class, 'preferred_school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignIdFor(Course::class, 'preferred_course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->unsignedTinyInteger('preferred_year_level')->nullable();

            $table->string('status')->default(ProjectStatus::Draft->value)->index();
            /**
             * Separate from status so a client can pause intake on an open
             * posting without withdrawing it from the board.
             */
            $table->boolean('applications_open')->default(true);
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
