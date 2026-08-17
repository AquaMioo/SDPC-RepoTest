<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the posting form's timeline, budget, team size and experience level.
 *
 * What a posting now carries: a title, a category, an industry, a description,
 * objectives and required skills. Nothing about money, dates or headcount.
 *
 * Downstream consequences, none of them recoverable from the schema:
 *
 * - The student dashboard's calendar and progress ring lose their last data
 *   source. Milestones went first, these dates were the fallback, and there is
 *   nothing left to derive a schedule from.
 * - The student board loses the budget it displayed and its "High reward" sort.
 * - Matching loses its experience-fit factor; the remaining weights are
 *   redistributed across skills, scope, availability and track record.
 * - A posting can no longer close by deadline — only by an administrator
 *   closing it or the client pausing intake.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const COLUMNS = [
        'team_size',
        'experience_level',
        'start_date',
        'target_delivery_date',
        'application_deadline',
        'expected_completion_date',
        'weekly_commitment',
        'budget_type',
        'budget_amount',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /* The index outlives its column on SQLite and blocks the drop. */
        if (Schema::hasColumn('projects', 'application_deadline')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropIndex(['application_deadline']);
            });
        }

        foreach (self::COLUMNS as $column) {
            if (! Schema::hasColumn('projects', $column)) {
                continue;
            }

            Schema::table('projects', function (Blueprint $table) use ($column) {
                $table->dropColumn($column);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedTinyInteger('team_size')->default(1);
            $table->string('experience_level')->default('any');
            $table->date('start_date')->nullable();
            $table->date('target_delivery_date')->nullable();
            $table->date('application_deadline')->nullable()->index();
            $table->date('expected_completion_date')->nullable();
            $table->string('weekly_commitment')->nullable();
            $table->string('budget_type')->default('fixed');
            $table->unsignedInteger('budget_amount')->nullable();
        });
    }
};
