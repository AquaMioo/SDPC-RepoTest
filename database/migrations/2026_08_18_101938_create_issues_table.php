<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reports one account makes about another.
 *
 * Both sides are cascading foreign keys: a report about a deleted account, or
 * from one, describes a situation that no longer exists and there is nothing
 * left for an administrator to decide.
 *
 * `handled_by` is nullOnDelete instead, because who resolved a report is a
 * record of what happened and should outlive the administrator's account.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('category');
            $table->text('description');
            $table->string('status')->default('pending');

            $table->string('resolution')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();

            $table->timestamps();

            // The admin queue reads open reports newest first.
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
