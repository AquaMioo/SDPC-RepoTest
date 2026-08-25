<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the redesigned business profile asks a company for.
 *
 * Industry and size are what a student reads first when deciding whether a
 * posting is worth applying to — "business consulting, 10–99 people" places a
 * client in a way a business name cannot. The tagline is the one line that
 * sits under the company name wherever it is shown.
 *
 * All three are nullable: a business that signed up before this existed is not
 * suddenly incomplete, and none of them gate anything.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->string('industry')->nullable()->after('business_description');
            $table->string('organization_size')->nullable()->after('industry');
            $table->string('tagline')->nullable()->after('organization_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->dropColumn(['industry', 'organization_size', 'tagline']);
        });
    }
};
