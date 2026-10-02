<?php

namespace App\Http\Controllers\Agreements;

use App\Enums\MilestoneStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agreements\SaveServiceDescriptionRequest;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Section VII of the memorandum: the Description of Services.
 *
 * Each service is an Objective (its title) and a Scope (what it covers), and
 * each is a phase of the project, written straight into agreement_milestones
 * before Turnover. Nothing is copied anywhere: Project Management, the
 * dashboards and the printed memorandum all read these rows, so what the two
 * sides agree here is exactly what gets tracked. The client sets each phase's
 * dates on the agreement's Timeline.
 *
 * Required: neither party may sign until there is at least one (SignAgreement).
 * Either party adds; only the author changes or removes; nothing moves once
 * somebody has signed. Turnover is never a service and is never touched here.
 */
class ServiceDescriptionController extends Controller
{
    /**
     * Add a service, just before Turnover.
     *
     * Turnover moves up one first, so the new row takes the free position and
     * the unique (agreement_id, position) index is never crossed.
     */
    public function store(SaveServiceDescriptionRequest $request, Team $currentTeam, Agreement $agreement): RedirectResponse
    {
        DB::transaction(function () use ($request, $agreement): void {
            $turnover = $agreement->milestones()->reorder()->orderByDesc('position')->lockForUpdate()->first();

            $position = $turnover?->position ?? 1;

            $turnover?->update(['position' => $turnover->position + 1]);

            $agreement->milestones()->create([
                'position' => $position,
                'title' => $request->string('objective')->trim()->toString(),
                'description' => $request->string('scope')->trim()->toString(),
                'added_by' => $request->user()->id,
                'amount' => 0,
                'status' => MilestoneStatus::Pending,
            ]);
        });

        return back()->with('success', 'Service added to Section VII.');
    }

    /**
     * Change a service the user added.
     */
    public function update(SaveServiceDescriptionRequest $request, Team $currentTeam, Agreement $agreement, AgreementMilestone $service): RedirectResponse
    {
        $service->update([
            'title' => $request->string('objective')->trim()->toString(),
            'description' => $request->string('scope')->trim()->toString(),
        ]);

        return back()->with('success', 'Service updated.');
    }

    /**
     * Remove a service the user added, closing the gap it leaves.
     */
    public function destroy(Request $request, Team $currentTeam, Agreement $agreement, AgreementMilestone $service): RedirectResponse
    {
        abort_unless($service->agreement_id === $agreement->id, 404);

        Gate::authorize('changeRequirement', [$agreement, $service]);

        DB::transaction(function () use ($agreement, $service): void {
            $position = $service->position;

            $service->delete();

            /* Lowest first, so each step down lands on a position just freed. */
            $agreement->milestones()
                ->where('position', '>', $position)
                ->get()
                ->each(fn (AgreementMilestone $milestone) => $milestone->update(['position' => $milestone->position - 1]));
        });

        return back()->with('success', 'Service removed from Section VII.');
    }
}
