<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the client ended the collaboration.
 *
 * The status says a project is completed; this says when, for the history
 * list and for anybody reading the record later. Both are nullable because
 * nothing before this sprint could complete a project at all.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
        });

        Schema::table('agreements', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('activated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            if (Schema::hasColumn('agreements', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });
    }
};
