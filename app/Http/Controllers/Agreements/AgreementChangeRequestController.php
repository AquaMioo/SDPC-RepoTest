<?php

namespace App\Http\Controllers\Agreements;

use App\Actions\Agreements\SupersedeAgreement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agreements\RequestAgreementChangesRequest;
use App\Models\Agreement;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;

class AgreementChangeRequestController extends Controller
{
    /**
     * Ask for the terms to be revised, which drafts the next version.
     */
    public function store(
        RequestAgreementChangesRequest $request,
        Team $currentTeam,
        Agreement $agreement,
        SupersedeAgreement $supersedeAgreement,
    ): RedirectResponse {
        $successor = $supersedeAgreement->handle(
            $agreement,
            $request->user(),
            $request->string('note')->toString(),
        );

        return redirect()
            ->route('agreements.show', [
                'current_team' => $currentTeam,
                'agreement' => $successor,
            ])
            ->with('success', 'Changes requested. Version '.$successor->version.' is open for editing.');
    }
}
