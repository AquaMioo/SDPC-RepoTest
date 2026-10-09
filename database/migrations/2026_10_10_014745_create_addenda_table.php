<?php

use App\Models\Agreement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Payment & Project Extension Addendum: a second document on a signed
 * Memorandum of Agreement, never a replacement for it.
 *
 * The client asks for it once the build is at least 80% done; both parties
 * add to Section II and set Section IV's target amount, then sign. Each
 * signature keeps the signer's GCash account as it stood at that moment
 * (encrypted, like users.gcash_number), so a signed copy never changes when a
 * profile does.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('addenda', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Agreement::class)->constrained()->cascadeOnDelete();

            /* The agreement's first, second, ... addendum: SDPC-2026-001-A1. */
            $table->unsignedSmallInteger('sequence');
            $table->string('reference')->unique();
            $table->string('status');

            /* Section IV's target amount, whole pesos (the two milestones are cut from it). */
            $table->unsignedInteger('total_amount')->nullable();

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('client_signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_signed_name')->nullable();
            $table->timestamp('client_signed_at')->nullable();
            $table->text('client_gcash_number')->nullable();

            $table->foreignId('student_signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('student_signed_name')->nullable();
            $table->timestamp('student_signed_at')->nullable();
            $table->text('student_gcash_number')->nullable();

            $table->timestamp('executed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->unique(['agreement_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addenda');
    }
};
