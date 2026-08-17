<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a client says about working with a student team, shown on the landing
 * page.
 *
 * One row per business rather than per project: the landing page is a wall of
 * businesses, not a feed, and a shop that has hired three teams should still
 * speak once. Writing again replaces what is there.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Team::class)->unique()->constrained()->cascadeOnDelete();
            /** Retained for attribution even if the staff member who wrote it leaves. */
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();

            $table->text('body');
            /** The writer's role at the business, e.g. "Owner" or "Manager". */
            $table->string('author_title')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
