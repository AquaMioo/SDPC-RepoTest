<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A project with a signed agreement running on it is in progress.
 *
 * Three buttons could knock one out of that state while the build ran: the
 * client's Archive project, the admin queue's Reopen / Close, and Close posting
 * on a report. Complete project refuses anything not in progress, so the client
 * of such a build was told "Only a project in progress can be completed" with
 * the work finished (sdpc.tech, 2026-10-04). The buttons are guarded now; this
 * puts back every project already caught, which also restores the locks that
 * read the status (the student's one build, the business's one posting).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('projects')
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['in_progress', 'completed'])
            ->whereExists(fn ($agreements) => $agreements
                ->selectRaw('1')
                ->from('agreements')
                ->whereColumn('agreements.project_id', 'projects.id')
                ->where('agreements.status', 'active')
                ->whereNull('agreements.deleted_at'))
            ->update([
                'status' => 'in_progress',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * Nothing to undo: the status each project was knocked into was never a
     * state it should have had.
     */
    public function down(): void
    {
        //
    }
};
