<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GitHub Repository Integration, at the level it actually belongs.
 *
 * The vision document asks for "sharing of repository links for collaborative
 * development and version control". A link on the student's profile is their
 * shop window; the repository a particular project is built in is a property of
 * that project, and both sides need to see it once the agreement is signed.
 *
 * A plain link, not an API integration: the platform points at the repository,
 * it does not read it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('repository_url')->nullable()->after('industry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('repository_url');
        });
    }
};
