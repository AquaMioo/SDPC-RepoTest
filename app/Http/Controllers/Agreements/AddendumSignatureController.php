<?php

namespace App\Http\Controllers\Agreements;

use App\Actions\Agreements\SignAddendum;
use App\Enums\AddendumStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agreements\SignAddendumRequest;
use App\Models\Addendum;
use App\Models\Agreement;
use App\Models\Team;
use App\Policies\AddendumPolicy;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * One party's signature on the addendum. The second executes it and makes the
 * down payment due (SignAddendum).
 */
class AddendumSignatureController extends Controller
{
    /**
     * Sign the addendum.
     */
    public function store(SignAddendumRequest $request, Team $currentTeam, Agreement $agreement, Addendum $addendum, SignAddendum $signAddendum, AddendumPolicy $policy): RedirectResponse
    {
        abort_unless($addendum->agreement_id === $agreement->id, HttpResponse::HTTP_NOT_FOUND);

        $party = $policy->partyFor($request->user(), $agreement);

        abort_if($party === null, HttpResponse::HTTP_FORBIDDEN);

        $signed = $signAddendum->handle(
            $addendum,
            $request->user(),
            $party,
            $request->string('signed_name')->trim()->toString(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $signed->status === AddendumStatus::Active
            ? __('Both sides have signed. The down payment is now due.')
            : __('Signed. The other side has been asked to sign.')]);

        return back();
    }
}
