<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks the Objective & Scope tasks an addendum's Section II put there.
 *
 * Their proof files and links stay locked from the client until the
 * addendum's final payment clears (Section VI's digital asset lock).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agreement_tasks', function (Blueprint $table) {
            $table->foreignId('addendum_id')->nullable()->constrained('addenda')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agreement_tasks', function (Blueprint $table) {
            $table->dropForeign(['addendum_id']);
        });

        Schema::table('agreement_tasks', function (Blueprint $table) {
            $table->dropColumn('addendum_id');
        });
    }
};
