<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which barangay of the served city a business is in.
 *
 * A plain string beside city and province, the same way those two are stored:
 * the barangays table is what the form offers and what validation checks
 * against, not a foreign key a profile has to be migrated onto. Optional, and
 * deliberately not part of the profile's completion score, so adding the field
 * did not drop every existing business below 100%.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_profiles', function (Blueprint $table): void {
            $table->string('barangay')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('client_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('client_profiles', 'barangay')) {
                $table->dropColumn('barangay');
            }
        });
    }
};
