<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One side asking the other to end a contract (owner, 2026-10-18).
 *
 * A denial is asked before both have signed, of a Memorandum of Agreement or
 * an Addendum; a termination is asked of a running collaboration. Either way
 * it takes the other side's answer: accepted, it cancels (denial) or completes
 * (termination) the contract; declined, everything carries on. The reason is
 * required. A termination carries the client's 1-5 rating when the client is
 * the one asking (or answering), because completing is rating.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contract_requests', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('type');
            $table->string('party');
            $table->foreignIdFor(User::class, 'requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('feedback')->nullable();
            $table->string('status');
            $table->foreignIdFor(User::class, 'decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_requests');
    }
};
