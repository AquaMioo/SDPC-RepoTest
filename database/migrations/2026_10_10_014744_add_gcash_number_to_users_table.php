<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The GCash account a client or student registers once in Settings, for the
 * Project Extension Addendum.
 *
 * Text, not a string: the value is stored encrypted (User casts it), and the
 * ciphertext of an 11-digit number is far longer than 255 characters' worth
 * of guarantee. It is never shown whole: every screen reads the masked form.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('gcash_number')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('gcash_number');
        });
    }
};
