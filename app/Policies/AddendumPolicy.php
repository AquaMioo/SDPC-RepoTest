<?php

namespace App\Policies;

use App\Enums\AddendumStatus;
use App\Enums\AgreementParty;
use App\Enums\AgreementStatus;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Addendum;
use App\Models\AddendumService;
use App\Models\Agreement;
use App\Models\Membership;
use App\Models\User;

/**
 * Who may do what with a Payment & Project Extension Addendum.
 *
 * The same two sides as the memorandum it extends (AgreementPolicy): the
 * signing student, and the client's business. The client asks for it and pays
 * it; both parties write Section II and Section IV and sign; nothing moves once
 * anybody signs. Like the memorandum, a draft stays between the two people
 * whose names go on it, and the student's teammates read it once it is signed.
 */
class AddendumPolicy
{
    /**
     * Determine whether the user can ask for an extension of the agreement.
     *
     * Someone on the business who may manage its projects, on a build still in
     * progress. The 80% threshold and the one-open-addendum rule are checked by
     * RequestProjectExtension, which can say which one failed.
     */
    public function create(User $user, Agreement $agreement): bool
    {
        return $agreement->status === AgreementStatus::Active
            && $this->isManagingClient($user, $agreement);
    }

    /**
     * Determine whether the user can read the addendum.
     */
    public function view(User $user, Addendum $addendum): bool
    {
        $agreement = $addendum->agreement;

        if ($this->partyFor($user, $agreement) !== null) {
            return true;
        }

        return in_array($addendum->status, [AddendumStatus::Active, AddendumStatus::Completed], true)
            && $this->isStudentsTeammate($user, $agreement);
    }

    /**
     * Determine whether the user can add to Section II or set Section IV's amount.
     *
     * Either party, on a draft nobody has signed, while the build it extends
     * is still running.
     */
    public function edit(User $user, Addendum $addendum): bool
    {
        $agreement = $addendum->agreement;
        $party = $this->partyFor($user, $agreement);

        if ($party === null
            || $addendum->status !== AddendumStatus::Draft
            || $addendum->hasAnySignature()
            || $agreement->status !== AgreementStatus::Active) {
            return false;
        }

        return $party === AgreementParty::Student || $this->isManagingClient($user, $agreement);
    }

    /**
     * Determine whether the user can change or remove one Section II service.
     *
     * Only its author; one with no author (the account is gone) is the client's.
     */
    public function changeService(User $user, Addendum $addendum, AddendumService $service): bool
    {
        if (! $this->edit($user, $addendum) || $service->addendum_id !== $addendum->id) {
            return false;
        }

        return $service->user_id === null
            ? $this->partyFor($user, $addendum->agreement) === AgreementParty::Client
            : $service->user_id === $user->id;
    }

    /**
     * Determine whether the user can sign: once per side.
     */
    public function sign(User $user, Addendum $addendum): bool
    {
        $agreement = $addendum->agreement;
        $party = $this->partyFor($user, $agreement);

        if ($party === null
            || ! $addendum->status->acceptsSignatures()
            || $agreement->status !== AgreementStatus::Active) {
            return false;
        }

        if ($party === AgreementParty::Client && ! $this->isManagingClient($user, $agreement)) {
            return false;
        }

        return ! $addendum->isSignedBy($party);
    }

    /**
     * Determine whether the user can withdraw the request: the client, before both have signed.
     */
    public function cancel(User $user, Addendum $addendum): bool
    {
        return $addendum->status->acceptsSignatures()
            && $this->isManagingClient($user, $addendum->agreement);
    }

    /**
     * Determine whether the user can pay a milestone: the client, once both have signed.
     */
    public function pay(User $user, Addendum $addendum): bool
    {
        return $addendum->status === AddendumStatus::Active
            && $this->isManagingClient($user, $addendum->agreement);
    }

    /**
     * Get the side of the agreement this user sits on, if any.
     */
    public function partyFor(User $user, Agreement $agreement): ?AgreementParty
    {
        if ($user->id === $agreement->student_id) {
            return AgreementParty::Student;
        }

        return $user->belongsToTeam($agreement->team) ? AgreementParty::Client : null;
    }

    /**
     * Whether the user acts for the business with the right to manage its projects.
     */
    protected function isManagingClient(User $user, Agreement $agreement): bool
    {
        return $this->partyFor($user, $agreement) === AgreementParty::Client
            && $user->hasTeamPermission($agreement->team, TeamPermission::ManageProjects);
    }

    /**
     * Whether the user is on a team the signing student owns.
     */
    protected function isStudentsTeammate(User $user, Agreement $agreement): bool
    {
        return Membership::query()
            ->where('user_id', $user->id)
            ->whereIn('team_id', Membership::query()
                ->select('team_id')
                ->where('user_id', $agreement->student_id)
                ->where('role', TeamRole::Owner->value))
            ->exists();
    }
}
