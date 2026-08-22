<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The places a business can say it operates in.
 *
 * One flat row per city or municipality, carrying its province, rather than a
 * provinces table with a cities table hanging off it. The screen only ever
 * needs "which provinces exist" and "which cities are in this one", and both
 * are one query against this shape — a second table would buy nothing.
 *
 * client_profiles keeps storing city and province as plain strings. The rows
 * here are what the form offers and what validation checks against, so no
 * profile has to be migrated onto foreign keys to benefit.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('province');
            $table->string('city');
            $table->string('slug')->unique();
            $table->timestamps();

            // The city select is always filtered by the chosen province.
            $table->index('province');
            $table->unique(['province', 'city']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
