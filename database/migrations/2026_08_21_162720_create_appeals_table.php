<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An account's answer to a decision taken about it.
 *
 * `account_status` is a snapshot of what was being appealed, taken when the
 * appeal was filed. Without it a granted appeal reads as an argument against
 * nothing: the status it was written about has by then been changed by the
 * granting itself.
 *
 * `reviewed_by` is nullOnDelete rather than cascading, for the same reason
 * issues.handled_by is — who decided an appeal is a record of what happened
 * and should outlive the administrator's account.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appeals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('account_status');
            $table->text('body');
            $table->string('status')->default('pending');

            $table->text('decision_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            // The admin queue reads open appeals oldest first.
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appeals');
    }
};
