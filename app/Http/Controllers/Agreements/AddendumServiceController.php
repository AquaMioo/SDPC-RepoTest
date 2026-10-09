<?php

namespace App\Http\Controllers\Agreements;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agreements\SaveAddendumServiceRequest;
use App\Models\Addendum;
use App\Models\AddendumService;
use App\Models\Agreement;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Section II of the addendum: the extended services, each an Objective and
 * its Scope. Required before anyone signs (SignAddendum). Either party adds;
 * only the author changes or removes; nothing moves once somebody has signed
 * (AddendumPolicy). Works like the memorandum's Section VII
 * (ServiceDescriptionController).
 */
class AddendumServiceController extends Controller
{
    /**
     * Add a service.
     */
    public function store(SaveAddendumServiceRequest $request, Team $currentTeam, Agreement $agreement, Addendum $addendum): RedirectResponse
    {
        abort_unless($addendum->agreement_id === $agreement->id, HttpResponse::HTTP_NOT_FOUND);

        $addendum->services()->create([
            'user_id' => $request->user()->id,
            'objective' => $request->string('objective')->trim()->toString(),
            'scope' => $request->string('scope')->trim()->toString(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service added to Section II.')]);

        return back();
    }

    /**
     * Change a service the user added.
     */
    public function update(SaveAddendumServiceRequest $request, Team $currentTeam, Agreement $agreement, Addendum $addendum, AddendumService $service): RedirectResponse
    {
        abort_unless($addendum->agreement_id === $agreement->id, HttpResponse::HTTP_NOT_FOUND);

        $service->update([
            'objective' => $request->string('objective')->trim()->toString(),
            'scope' => $request->string('scope')->trim()->toString(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service updated.')]);

        return back();
    }

    /**
     * Remove a service the user added.
     */
    public function destroy(Request $request, Team $currentTeam, Agreement $agreement, Addendum $addendum, AddendumService $service): RedirectResponse
    {
        abort_unless($addendum->agreement_id === $agreement->id, HttpResponse::HTTP_NOT_FOUND);

        Gate::authorize('changeService', [$addendum, $service]);

        $service->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service removed from Section II.')]);

        return back();
    }
}
