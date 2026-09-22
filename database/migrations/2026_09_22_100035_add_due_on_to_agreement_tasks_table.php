<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every task gets a deadline.
 *
 * Nullable because tasks written before this had none; they read "No
 * deadline" until the student sets one. A new task in a Design or Build phase
 * must carry one (SaveTaskRequest), and after that it only moves with the
 * client's approval (deadline_change_requests).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agreement_tasks', function (Blueprint $table) {
            $table->date('due_on')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agreement_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('agreement_tasks', 'due_on')) {
                $table->dropColumn('due_on');
            }
        });
    }
};
