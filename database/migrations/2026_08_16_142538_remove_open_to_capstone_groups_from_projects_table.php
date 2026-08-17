<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the "open to capstone groups" flag.
 *
 * Nothing ever read it — no query filtered on it and no screen showed it to a
 * student — so it asked the client a question that changed nothing. The team
 * size field already says how many students a posting wants.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('open_to_capstone_groups');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('open_to_capstone_groups')->default(true);
        });
    }
};
