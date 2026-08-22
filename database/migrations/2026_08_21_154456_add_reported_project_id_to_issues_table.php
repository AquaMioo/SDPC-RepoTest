<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a report name a posting as well as an account.
 *
 * Nullable, because most reports are still about a person. `reported_user_id`
 * stays required either way: a posting always belongs to a team, and an
 * administrator resolving the report needs an account to act on — closing the
 * posting alone leaves whoever wrote it free to write another.
 *
 * Cascading for the same reason both sides already cascade: a report about a
 * deleted posting describes a situation that no longer exists.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->foreignId('reported_project_id')
                ->nullable()
                ->after('reported_user_id')
                ->constrained('projects')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropForeign(['reported_project_id']);
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn('reported_project_id');
        });
    }
};
