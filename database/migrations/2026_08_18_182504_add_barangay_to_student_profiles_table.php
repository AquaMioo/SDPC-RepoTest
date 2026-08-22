<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which barangay of San Jose Del Monte a student is in.
 *
 * Kept beside `location` rather than replacing it. This column is the part a
 * list can know and a query can filter on; `location` stays as the free text
 * for the subdivision, phase or landmark no list will ever hold — the case
 * where an address simply is not on any map.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('barangay')->nullable()->after('location')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('student_profiles', 'barangay')) {
                $table->dropIndex(['barangay']);
                $table->dropColumn('barangay');
            }
        });
    }
};
