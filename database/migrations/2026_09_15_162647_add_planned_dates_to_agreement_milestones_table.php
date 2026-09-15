<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The student's working schedule for each phase.
 *
 * starts_on / ends_on are contract terms: they were signed, and signed terms
 * only move through a change request. The timeline on Project Management is
 * the student's plan, which moves as the work does, so it lives beside the
 * agreed dates instead of over them. Null means "as agreed".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreement_milestones', function (Blueprint $table): void {
            $table->date('planned_starts_on')->nullable()->after('ends_on');
            $table->date('planned_ends_on')->nullable()->after('planned_starts_on');
        });
    }

    public function down(): void
    {
        Schema::table('agreement_milestones', function (Blueprint $table): void {
            if (Schema::hasColumn('agreement_milestones', 'planned_ends_on')) {
                $table->dropColumn('planned_ends_on');
            }
        });

        Schema::table('agreement_milestones', function (Blueprint $table): void {
            if (Schema::hasColumn('agreement_milestones', 'planned_starts_on')) {
                $table->dropColumn('planned_starts_on');
            }
        });
    }
};
