<?php

use App\Enums\MilestoneStatus;
use App\Models\Agreement;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The agreed pieces of work, in order, each with a price and a window.
 *
 * One row set feeds four screens: the agreement's Pricing block, the
 * agreement's Timeline block, the dashboard progress ring and the Project
 * process bars. Before this table existed the last three had nothing to read —
 * `projects` lost its milestones and dates, so progress was a lifecycle stage
 * dressed up as a percentage.
 *
 * Every status change here is a person's decision, which is what makes the
 * derived progress figure honest.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agreement_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Agreement::class)->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('position');
            $table->string('title');
            $table->text('description')->nullable();

            /* Whole pesos. The platform never splits a centavo. */
            $table->unsignedInteger('amount')->default(0);

            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            $table->string('status')->default(MilestoneStatus::Pending->value)->index();
            $table->text('review_note')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignIdFor(User::class, 'approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['agreement_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agreement_milestones');
    }
};
