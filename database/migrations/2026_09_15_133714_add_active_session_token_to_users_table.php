<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which browser holds the account.
 *
 * One account, one device at a time. The session that holds the account
 * carries the same token in its own data; any other session signed in as this
 * user is turned away. Null means nobody has claimed it since this column
 * arrived, or a password reset took it back. See App\Support\AccountSession.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('active_session_token', 64)->nullable()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'active_session_token')) {
                $table->dropColumn('active_session_token');
            }
        });
    }
};
