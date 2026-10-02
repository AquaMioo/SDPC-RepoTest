<?php

namespace App\Http\Controllers\Agreements;

use App\Enums\MemorandumSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agreements\SaveMemorandumRequirementRequest;
use App\Models\Agreement;
use App\Models\AgreementRequirement;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * "Add requirement" on the memorandum's optional sections.
 *
 * Sections IV, V, VI, VIII and IX end with a line for more of the same. Either
 * party appends to them here; the school's base wording above is never
 * touched. A line belongs to whoever wrote it: only they change or remove it,
 * and only until somebody signs. A section left empty prints without its
 * placeholder.
 *
 * Both route models are declared in URL order, and the controller checks the
 * line belongs to the agreement (see .ai/rules/controllers-agreements.md).
 */
class MemorandumRequirementController extends Controller
{
    /**
     * Add a line to a section.
     */
    public function store(SaveMemorandumRequirementRequest $request, Team $currentTeam, Agreement $agreement): RedirectResponse
    {
        $agreement->requirements()->create([
            'section' => MemorandumSection::from($request->string('section')->toString()),
            'user_id' => $request->user()->id,
            'body' => $request->string('body')->trim()->toString(),
        ]);

        return back()->with('success', 'Requirement added.');
    }

    /**
     * Change a line the user added.
     */
    public function update(SaveMemorandumRequirementRequest $request, Team $currentTeam, Agreement $agreement, AgreementRequirement $requirement): RedirectResponse
    {
        $requirement->update(['body' => $request->string('body')->trim()->toString()]);

        return back()->with('success', 'Requirement updated.');
    }

    /**
     * Remove a line the user added.
     */
    public function destroy(Request $request, Team $currentTeam, Agreement $agreement, AgreementRequirement $requirement): RedirectResponse
    {
        abort_unless($requirement->agreement_id === $agreement->id, 404);

        Gate::authorize('changeRequirement', [$agreement, $requirement]);

        $requirement->delete();

        return back()->with('success', 'Requirement removed.');
    }
}
