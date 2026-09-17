<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Take SheerID out of student_verifications.
 *
 * SheerID was never switched on in production, so no rows are expected — any
 * that exist are deleted first, because the provider cast no longer knows the
 * value and would fail to read them. The two columns only SheerID ever wrote
 * go with it, and the table's default provider becomes the school-email check,
 * the only one left. Rows from that check are untouched.
 *
 * Index first, then columns, in separate statements, each guarded: SQLite
 * revalidates the table after a drop and does not roll a failed statement
 * back, so a half-applied run has to be finishable (.ai/rules/migrations.md).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('student_verifications')->where('provider', 'sheerid')->delete();

        if (Schema::hasIndex('student_verifications', ['external_id'])) {
            Schema::table('student_verifications', function (Blueprint $table) {
                $table->dropIndex(['external_id']);
            });
        }

        foreach (['external_id', 'redirect_url'] as $column) {
            if (Schema::hasColumn('student_verifications', $column)) {
                Schema::table('student_verifications', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        Schema::table('student_verifications', function (Blueprint $table) {
            $table->string('provider')->default('school_email')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * The columns come back empty; deleted rows do not come back. The default
     * provider is left on the school-email check, which is the only provider
     * the code still knows.
     */
    public function down(): void
    {
        Schema::table('student_verifications', function (Blueprint $table) {
            $table->string('external_id')->nullable()->index()->after('status');
            $table->string('redirect_url', 2048)->nullable()->after('external_id');
        });
    }
};
