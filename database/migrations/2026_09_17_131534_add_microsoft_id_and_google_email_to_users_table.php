<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two more ways into an account, stored beside the one that already exists.
 *
 * `microsoft_id` is the school Microsoft account a student signed up or signed
 * in with. `google_email` is the address of the Google account in `google_id`,
 * kept so a student can see which personal account they bound — the id alone
 * means nothing to a person.
 *
 * Only adds columns. Every Google account linked so far was matched on the
 * account's own address, so that address is copied across as its
 * `google_email`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('microsoft_id')->nullable()->unique()->after('google_id');
            $table->string('google_email')->nullable()->index()->after('google_id');
        });

        DB::table('users')
            ->whereNotNull('google_id')
            ->whereNull('google_email')
            ->update(['google_email' => DB::raw('LOWER(email)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['microsoft_id']);
            $table->dropIndex(['google_email']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['microsoft_id', 'google_email']);
        });
    }
};
