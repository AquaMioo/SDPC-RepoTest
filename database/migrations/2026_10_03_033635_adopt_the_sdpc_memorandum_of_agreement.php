<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The SDPC Memorandum of Agreement replaces the earlier memorandum.
 *
 * Section VII (Description of Services) is now where the phases come from:
 * each entry is a phase, named by its Objective and described by its Scope,
 * and added_by records who wrote it, because only its author may change or
 * remove it. Turnover is still the last phase.
 *
 * Agreements nobody has signed move to the new memorandum, the way the
 * earlier one was adopted (2026_09_16_184127). Their seeded Design and Build
 * placeholders go: they are the legacy phases the new Section VII replaces,
 * nobody wrote a scope for them, and an unsigned agreement has no tasks on
 * them. Turnover stays, with any dates the client gave it. Anything somebody
 * signed keeps the words and phases that signature was given for.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agreement_milestones', function (Blueprint $table) {
            $table->foreignIdFor(User::class, 'added_by')->nullable()->after('description')->constrained('users')->nullOnDelete();
        });

        $unsigned = DB::table('agreements')
            ->whereIn('status', ['draft', 'awaiting_signatures'])
            ->whereNotExists(fn ($signatures) => $signatures
                ->selectRaw('1')
                ->from('agreement_signatures')
                ->whereColumn('agreement_signatures.agreement_id', 'agreements.id'))
            ->pluck('id');

        foreach ($unsigned as $agreementId) {
            DB::transaction(function () use ($agreementId): void {
                DB::table('agreements')->where('id', $agreementId)->update(['template' => 'sdpc_moa']);

                $turnover = DB::table('agreement_milestones')
                    ->where('agreement_id', $agreementId)
                    ->orderByDesc('position')
                    ->first();

                if ($turnover === null) {
                    return;
                }

                DB::table('agreement_milestones')
                    ->where('agreement_id', $agreementId)
                    ->where('id', '!=', $turnover->id)
                    ->whereNull('added_by')
                    ->whereNotExists(fn ($tasks) => $tasks
                        ->selectRaw('1')
                        ->from('agreement_tasks')
                        ->whereColumn('agreement_tasks.agreement_milestone_id', 'agreement_milestones.id'))
                    ->delete();

                /* Close the gap the placeholders left, Turnover still last. */
                $remaining = DB::table('agreement_milestones')
                    ->where('agreement_id', $agreementId)
                    ->orderBy('position')
                    ->pluck('id');

                foreach ($remaining as $index => $milestoneId) {
                    DB::table('agreement_milestones')->where('id', $milestoneId)->update(['position' => 1000 + $index]);
                }

                foreach ($remaining as $index => $milestoneId) {
                    DB::table('agreement_milestones')->where('id', $milestoneId)->update(['position' => $index + 1]);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * The deleted placeholders are not recreated: they held no work, and the
     * earlier memorandum's drafts get Design and Build from DraftAgreement.
     */
    public function down(): void
    {
        DB::table('agreements')->where('template', 'sdpc_moa')->update(['template' => 'memorandum']);

        Schema::table('agreement_milestones', function (Blueprint $table) {
            $table->dropForeign(['added_by']);
        });

        Schema::table('agreement_milestones', function (Blueprint $table) {
            $table->dropColumn('added_by');
        });
    }
};
