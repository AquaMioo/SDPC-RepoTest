<?php

use App\Enums\TaskStatus;
use App\Models\AgreementMilestone;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The checklist inside each phase of a signed agreement.
 *
 * The student writes the items and checks them off; only the client can
 * verify one. Project progress is the share of these the client verified —
 * see App\Actions\Agreements\SummariseProgress.
 *
 * Position is indexed but deliberately not unique. agreement_milestones is
 * unique on its position, and that is why AgreementController has to park rows
 * before it renumbers them; a checklist is reordered far more often than a
 * contract, so it does not take on the same trap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(AgreementMilestone::class)->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('position');
            $table->string('title');
            $table->text('description')->nullable();

            $table->string('status')->default(TaskStatus::Open->value)->index();

            /* What the student offered as proof when they checked it off. */
            $table->text('proof_note')->nullable();
            $table->string('proof_url', 2048)->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_name')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->foreignIdFor(User::class, 'submitted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('verified_at')->nullable();
            $table->foreignIdFor(User::class, 'verified_by')->nullable()->constrained('users')->nullOnDelete();

            /* Why the client sent it back, shown to the student until they resubmit. */
            $table->text('review_note')->nullable();

            $table->timestamps();

            $table->index(['agreement_milestone_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_tasks');
    }
};
