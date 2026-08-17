<?php

use App\Enums\AgreementStatus;
use App\Models\Application;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The contract between one client and one student for one project.
 *
 * A posting deliberately carries no money, dates or headcount — those were
 * removed from `projects` because a brief is not a commitment. They live here
 * instead, where they are negotiated: scope, milestone pricing and phase dates
 * are agreed after the client accepts a student, and are what both sides sign.
 *
 * A change request does not edit a signed agreement: it writes the next
 * version as a new row and points the old one's `superseded_by` at it, so the
 * contract log is append-only and a signature always refers to terms that
 * still read exactly as they did when it was given. That is why the unique key
 * is (application_id, version) rather than application_id alone — one live
 * agreement per accepted application, with its history beside it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Project::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Application::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Team::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'student_id')->constrained('users')->cascadeOnDelete();

            /*
             * Human-quotable on paper and in a message: SDPC-2026-014. Shared
             * by every version of the same contract, which is why it is unique
             * with the version rather than on its own.
             */
            $table->string('reference')->index();
            $table->unsignedSmallInteger('version')->default(1);

            $table->string('status')->default(AgreementStatus::Draft->value)->index();

            $table->text('scope_summary')->nullable();
            /* One deliverable per entry, mirroring the posting's objectives. */
            $table->json('deliverables')->nullable();

            $table->text('intellectual_property_terms')->nullable();
            $table->text('confidentiality_terms')->nullable();
            $table->text('academic_terms')->nullable();

            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            /*
             * Denormalised from the milestones so a list of agreements does not
             * have to aggregate a second table to show a figure. Recomputed
             * whenever a milestone changes, never written by hand.
             */
            $table->unsignedInteger('total_amount')->default(0);

            $table->timestamp('activated_at')->nullable();
            $table->foreignId('superseded_by')->nullable()->constrained('agreements')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['application_id', 'version']);
            $table->unique(['reference', 'version']);
            $table->index(['project_id', 'status']);
            $table->index(['student_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agreements');
    }
};
