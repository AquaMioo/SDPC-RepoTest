<?php

namespace App\Policies;

use App\Enums\AgreementParty;
use App\Enums\ContractRequestStatus;
use App\Enums\TeamPermission;
use App\Models\ContractRequest;
use App\Models\User;

/**
 * Who answers a denial or a termination request: the other side, and only
 * while it is waiting. The side that asked may take it back.
 */
class ContractRequestPolicy
{
    /**
     * Determine whether the user can accept or decline the request.
     */
    public function decide(User $user, ContractRequest $contractRequest): bool
    {
        if ($contractRequest->status !== ContractRequestStatus::Pending) {
            return false;
        }

        $agreement = $contractRequest->agreement();
        $answering = $contractRequest->party->counterparty();

        return $answering === AgreementParty::Student
            ? $user->id === $agreement->student_id
            : $user->belongsToTeam($agreement->team)
                && $user->hasTeamPermission($agreement->team, TeamPermission::ManageProjects);
    }

    /**
     * Determine whether the user can withdraw the request: the person who asked.
     */
    public function withdraw(User $user, ContractRequest $contractRequest): bool
    {
        return $contractRequest->status === ContractRequestStatus::Pending
            && $contractRequest->requested_by === $user->id;
    }
}
