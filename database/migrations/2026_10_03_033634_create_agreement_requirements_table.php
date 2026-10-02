<?php

use App\Models\Agreement;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What either side adds to the Memorandum of Agreement.
 *
 * Sections IV, V, VI, VIII and IX of the SDPC MOA end in "add more here if
 * required". Each row is one such addition, appended after the section's base
 * wording on the screen and on the printed copy. user_id is who wrote it: only
 * they may change or remove it, and only until somebody signs. Section VII's
 * entries are the agreement's phases (agreement_milestones), not rows here.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agreement_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Agreement::class)->constrained()->cascadeOnDelete();
            /* App\Enums\MemorandumSection, never 'services'. */
            $table->string('section', 30);
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['agreement_id', 'section']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agreement_requirements');
    }
};
