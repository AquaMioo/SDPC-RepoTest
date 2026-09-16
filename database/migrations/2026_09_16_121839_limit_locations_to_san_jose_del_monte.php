<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The platform serves San Jose Del Monte, Bulacan, and nowhere else.
 *
 * The locations list used to carry all 24 Bulacan towns. The seeder now seeds
 * San Jose Del Monte only, but it runs updateOrCreate and never deletes, so the
 * other 23 rows would stay offered on every database that already had them —
 * production included. This removes them once.
 *
 * Only the locations table is touched. client_profiles stores city and province
 * as plain strings, so a business that already named another town keeps what
 * it saved; it is asked to pick San Jose Del Monte the next time it edits its
 * contacts. San Jose Del Monte's barangays hang off the row that stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('locations')
            ->where(fn ($query) => $query
                ->where('province', '!=', 'Bulacan')
                ->orWhere('city', '!=', 'San Jose Del Monte'))
            ->delete();
    }

    /**
     * Nothing to restore by hand: widening the scope again is a change to the
     * list in ClientModuleTaxonomySeeder, followed by re-running it.
     */
    public function down(): void
    {
        //
    }
};
