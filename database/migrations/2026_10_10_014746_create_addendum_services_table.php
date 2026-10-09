<?php

use App\Models\Addendum;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section II of the addendum: each extended service an Objective and its
 * Scope, added by either party, exactly like the memorandum's Section VII.
 * Once the down payment clears, each becomes one task in Project
 * Management's Objective & Scope.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('addendum_services', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Addendum::class)->constrained('addenda')->cascadeOnDelete();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->string('objective', 120);
            $table->text('scope');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addendum_services');
    }
};
