<?php

use App\Enums\DeadlineRequestStatus;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\AgreementTask;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student asking the client to move a date.
 *
 * Deadlines are flexible but watched: the student may propose, only the client
 * may approve, and the date only changes when they do. One row per ask, kept
 * after it is decided, so the history of every extension survives.
 *
 * It is about either a task's deadline or the final deadline — the end of the
 * last phase, Turnover — so exactly one of the two foreign keys is set.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deadline_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Agreement::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(AgreementTask::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(AgreementMilestone::class)->nullable()->constrained()->cascadeOnDelete();

            $table->foreignIdFor(User::class, 'requested_by')->nullable()->constrained('users')->nullOnDelete();
            /* The date standing when the request was made, and the one asked for. */
            $table->date('previous_on')->nullable();
            $table->date('proposed_on');
            $table->text('reason')->nullable();

            $table->string('status')->default(DeadlineRequestStatus::Pending->value)->index();
            $table->foreignIdFor(User::class, 'decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            $table->timestamps();

            $table->index(['agreement_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deadline_change_requests');
    }
};
