<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The email domain a school issues addresses on.
 *
 * This is what turns "I typed an address that looks academic" into "I proved
 * I can open mail at an address this school hands out". Nullable, because
 * plenty of schools on the list issue no student mail at all — and a school
 * without a domain simply cannot be used for this route, rather than becoming
 * a hole in it.
 *
 * Stored bare and lowercase: "sti.edu.ph", never "@sti.edu.ph" and never a
 * URL. SchoolEmailVerifier matches the part after the @ for exact equality
 * against this, and a stray @ or trailing slash would silently never match.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            /*
             * Not unique. Some institutions run several campuses as separate
             * rows on one domain, and a unique index would make the second
             * campus unenterable.
             */
            $table->string('domain')->nullable()->after('city')->index();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'domain')) {
                /* Index before column — see .ai/rules/migrations.md. */
                $table->dropIndex(['domain']);
                $table->dropColumn('domain');
            }
        });
    }
};
