<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A picture the account uploaded itself.
 *
 * Kept apart from `avatar`, which holds the URL Google hands back at sign in:
 * that column is owned by the provider and is rewritten on every login, so an
 * upload stored there would not survive the next sign in. Two columns let an
 * uploaded picture win while still falling back to the Google one.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('avatar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'avatar_path')) {
                $table->dropColumn('avatar_path');
            }
        });
    }
};
