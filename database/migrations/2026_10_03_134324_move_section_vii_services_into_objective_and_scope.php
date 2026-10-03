<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Section VII adds to the Objective and Scope phases; it never adds phases.
 *
 * The SDPC memorandum's Project Management has three phases: Objective,
 * Scope and Turnover. Each Section VII service is an Objective (its title)
 * and a Scope (its description), kept as an agreement_requirements row in
 * section 'services'. When the agreement starts, every objective becomes a
 * task in the Objective phase and every scope a task in the Scope phase —
 * two separate things (App\Actions\Agreements\SeedServiceTasks).
 *
 * The first version (2026_10_03_033635) made each service a phase of its
 * own. Every SDPC agreement still open, or active with no task written yet,
 * is moved to the new shape: its services become requirement rows, its
 * service phases become one Objective and one Scope phase spanning their
 * dates, and an active one gets its tasks seeded. An agreement with work
 * already tracked on its phases is left exactly as it is.
 *
 * A scope can run to 2,000 characters, so a task's title becomes text.
 *
 * agreement_milestones.added_by is no longer written or read, but stays: the
 * services' authors are copied out of it here, and dropping a foreign key
 * rebuilds the table on SQLite, which cascades away every task under it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreement_requirements', function (Blueprint $table) {
            /* Section VII's Objective; the body is its Scope. Null elsewhere. */
            $table->string('title', 120)->nullable()->after('section');
        });

        Schema::table('agreement_tasks', function (Blueprint $table) {
            $table->text('title')->change();
        });

        $agreements = DB::table('agreements')
            ->where('template', 'sdpc_moa')
            ->whereIn('status', ['draft', 'awaiting_signatures', 'active'])
            ->get(['id', 'status']);

        foreach ($agreements as $agreement) {
            DB::transaction(fn () => $this->reshape($agreement->id, $agreement->status === 'active'));
        }
    }

    public function down(): void
    {
        Schema::table('agreement_requirements', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }

    /**
     * Turn one agreement's service phases into Objective, Scope, Turnover.
     */
    private function reshape(int $agreementId, bool $isActive): void
    {
        $phases = DB::table('agreement_milestones')
            ->where('agreement_id', $agreementId)
            ->orderBy('position')
            ->get();

        $turnover = $phases->last();

        if ($turnover === null) {
            return;
        }

        $services = $phases->slice(0, -1)->values();

        $alreadyReshaped = $services->pluck('title')->all() === ['Objective', 'Scope']
            && $services->every(fn ($phase) => $phase->added_by === null);

        $hasWork = DB::table('agreement_tasks')
            ->whereIn('agreement_milestone_id', $phases->pluck('id'))
            ->exists();

        if ($alreadyReshaped || $hasWork) {
            return;
        }

        $now = now();

        foreach ($services as $service) {
            DB::table('agreement_requirements')->insert([
                'agreement_id' => $agreementId,
                'section' => 'services',
                'title' => mb_substr((string) $service->title, 0, 120),
                'body' => (string) ($service->description ?? ''),
                'user_id' => $service->added_by,
                'created_at' => $service->created_at ?? $now,
                'updated_at' => $now,
            ]);
        }

        /* The two phases run across the dates the services had, which may overlap. */
        $startsOn = $services->pluck('starts_on')->filter()->min();
        $endsOn = $services->pluck('ends_on')->filter()->max();

        DB::table('agreement_milestones')->whereIn('id', $services->pluck('id'))->delete();
        DB::table('agreement_milestones')->where('id', $turnover->id)->update(['position' => 1000]);

        $phaseIds = [];

        foreach (['Objective', 'Scope'] as $index => $title) {
            $phaseIds[$title] = DB::table('agreement_milestones')->insertGetId([
                'agreement_id' => $agreementId,
                'position' => $index + 1,
                'title' => $title,
                'amount' => 0,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('agreement_milestones')->where('id', $turnover->id)->update(['position' => 3]);

        if (! $isActive) {
            return;
        }

        /* Work has started: every objective and every scope is a task to deliver. */
        $entries = DB::table('agreement_requirements')
            ->where('agreement_id', $agreementId)
            ->where('section', 'services')
            ->orderBy('id')
            ->get();

        foreach (['Objective' => 'title', 'Scope' => 'body'] as $phase => $column) {
            $position = 0;

            foreach ($entries as $entry) {
                if (trim((string) $entry->{$column}) === '') {
                    continue;
                }

                DB::table('agreement_tasks')->insert([
                    'agreement_milestone_id' => $phaseIds[$phase],
                    'position' => ++$position,
                    'title' => $entry->{$column},
                    'status' => 'open',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
