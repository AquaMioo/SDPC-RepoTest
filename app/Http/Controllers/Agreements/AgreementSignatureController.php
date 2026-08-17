<?php

namespace App\Http\Controllers\Agreements;

use App\Actions\Agreements\SignAgreement;
use App\Enums\AgreementParty;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agreements\SignAgreementRequest;
use App\Models\Agreement;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;

class AgreementSignatureController extends Controller
{
    /**
     * Sign the agreement on behalf of the acting party.
     *
     * The party is derived from the signed-in user rather than taken from the
     * request: a client must never be able to post a signature marked
     * "student", and the shape of the form is not a security boundary.
     */
    public function store(
        SignAgreementRequest $request,
        Team $currentTeam,
        Agreement $agreement,
        SignAgreement $signAgreement,
    ): RedirectResponse {
        $user = $request->user();

        $party = $user->id === $agreement->student_id
            ? AgreementParty::Student
            : AgreementParty::Client;

        $signAgreement->handle(
            $agreement,
            $user,
            $party,
            $request->string('signed_name')->toString(),
            $request->acknowledgements(),
            $request,
        );

        return back()->with('success', $agreement->refresh()->isFullySigned()
            ? 'Agreement signed. The project has started.'
            : 'Agreement signed. Waiting on the other party.');
    }
}
