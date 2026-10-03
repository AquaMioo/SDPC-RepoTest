<?php

namespace App\Http\Controllers\Agreements;

use App\Enums\MemorandumSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agreements\SaveServiceDescriptionRequest;
use App\Models\Agreement;
use App\Models\AgreementRequirement;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Section VII of the memorandum: the Description of Services.
 *
 * Each service is an Objective (the row's title) and a Scope (its body), kept
 * with the other additions in agreement_requirements. It never adds a phase:
 * the phases are Objective, Scope and Turnover, and when the agreement starts
 * each objective becomes a task in the Objective phase and each scope a task
 * in the Scope phase (SeedServiceTasks) — two separate things.
 *
 * Required: neither party may sign until there is at least one (SignAgreement).
 * Either party adds; only the author changes or removes; nothing moves once
 * somebody has signed (AgreementPolicy).
 */
class ServiceDescriptionController extends Controller
{
    /**
     * Add a service.
     */
    public function store(SaveServiceDescriptionRequest $request, Team $currentTeam, Agreement $agreement): RedirectResponse
    {
        $agreement->requirements()->create([
            'section' => MemorandumSection::Services,
            'title' => $request->string('objective')->trim()->toString(),
            'body' => $request->string('scope')->trim()->toString(),
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Service added to Section VII.');
    }

    /**
     * Change a service the user added.
     */
    public function update(SaveServiceDescriptionRequest $request, Team $currentTeam, Agreement $agreement, AgreementRequirement $service): RedirectResponse
    {
        $service->update([
            'title' => $request->string('objective')->trim()->toString(),
            'body' => $request->string('scope')->trim()->toString(),
        ]);

        return back()->with('success', 'Service updated.');
    }

    /**
     * Remove a service the user added.
     */
    public function destroy(Request $request, Team $currentTeam, Agreement $agreement, AgreementRequirement $service): RedirectResponse
    {
        abort_unless($service->agreement_id === $agreement->id && $service->section === MemorandumSection::Services, 404);

        Gate::authorize('changeRequirement', [$agreement, $service]);

        $service->delete();

        return back()->with('success', 'Service removed from Section VII.');
    }
}
