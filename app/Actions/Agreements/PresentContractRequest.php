<?php

namespace App\Actions\Agreements;

use App\Enums\AgreementParty;
use App\Enums\ContractRequestType;
use App\Models\Addendum;
use App\Models\ContractRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * A denial or termination request waiting for an answer, shaped for the screen
 * it is answered on: who asked and why, and what this viewer may do about it.
 */
class PresentContractRequest
{
    /**
     * Present the request to the given viewer, or nothing.
     *
     * @return array{id: int, type: string, subject: string, party: string, partyLabel: string, requestedBy: string|null, reason: string, requestedAt: string|null, canDecide: bool, canWithdraw: bool, needsRating: bool}|null
     */
    public function handle(?ContractRequest $request, User $viewer): ?array
    {
        if ($request === null) {
            return null;
        }

        $canDecide = Gate::forUser($viewer)->allows('decide', $request);

        return [
            'id' => $request->id,
            'type' => $request->type->value,
            'subject' => $request->subject instanceof Addendum ? 'addendum' : 'agreement',
            'party' => $request->party->value,
            'partyLabel' => $request->party->label(),
            'requestedBy' => $request->requester?->name,
            'reason' => $request->reason,
            'requestedAt' => $request->created_at?->format('j M Y, g:i a'),
            'canDecide' => $canDecide,
            'canWithdraw' => Gate::forUser($viewer)->allows('withdraw', $request),
            /* The client agreeing to a termination the student asked for rates the work. */
            'needsRating' => $canDecide
                && $request->type === ContractRequestType::Termination
                && $request->party === AgreementParty::Student,
        ];
    }
}
