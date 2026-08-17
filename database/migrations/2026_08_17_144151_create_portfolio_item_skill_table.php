<?php

use App\Models\Skill;
use App\Models\StudentPortfolioItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The technologies a portfolio piece was actually built with.
 *
 * Named for the same convention as skill_student_profile: singular model names
 * in alphabetical order.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('portfolio_item_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(StudentPortfolioItem::class, 'portfolio_item_id')
                ->constrained('student_portfolio_items')
                ->cascadeOnDelete();
            $table->foreignIdFor(Skill::class)->constrained()->cascadeOnDelete();

            $table->unique(['portfolio_item_id', 'skill_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_item_skill');
    }
};
