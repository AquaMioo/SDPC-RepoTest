<?php

namespace App\Policies;

use App\Enums\AgreementParty;
use App\Enums\TeamPermission;
use App\Models\Agreement;
use App\Models\User;

class AgreementPolicy
{
    /**
     * Determine whether the user can read the agreement.
     *
     * Only the two parties. An agreement carries money, scope and signatures,
     * so there is no "anyone on the platform" case here the way there is for a
     * posting — not even another member of the student's school.
     */
    public function view(User $user, Agreement $agreement): bool
    {
        return $this->partyFor($user, $agreement) !== null;
    }

    /**
     * Determine whether the user can change the terms.
     *
     * The client drafts, the student reads. Both is not an option: an
     * agreement one side can rewrite is not a contract, and the student's
     * lever is the change request, which supersedes rather than edits.
     *
     * Editing also closes the moment anybody signs, so nothing can move
     * underneath a signature already given.
     */
    public function update(User $user, Agreement $agreement): bool
    {
        return $this->partyFor($user, $agreement) === AgreementParty::Client
            && $user->hasTeamPermission($agreement->team, TeamPermission::ManageProjects)
            && $agreement->status->isEditable()
            && ! $agreement->signatures()->exists();
    }

    /**
     * Determine whether the user can sign.
     *
     * Once per side, and never on a superseded or cancelled agreement.
     */
    public function sign(User $user, Agreement $agreement): bool
    {
        $party = $this->partyFor($user, $agreement);

        if ($party === null || ! $agreement->status->acceptsSignatures()) {
            return false;
        }

        if ($party === AgreementParty::Client
            && ! $user->hasTeamPermission($agreement->team, TeamPermission::ManageProjects)) {
            return false;
        }

        return ! $agreement->isSignedBy($party);
    }

    /**
     * Determine whether the user can ask for the terms to change.
     *
     * The student's counterweight to the client holding the pen. Available
     * right up until the agreement is active, including after the student has
     * read terms they do not accept.
     */
    public function requestChanges(User $user, Agreement $agreement): bool
    {
        return $this->partyFor($user, $agreement) !== null
            && $agreement->status->acceptsSignatures();
    }

    /**
     * Get the side of the agreement this user sits on, if any.
     *
     * A client is recognised through team membership rather than by id: any
     * member of the business with the right permission acts for it, which is
     * how the rest of the client module already works.
     */
    protected function partyFor(User $user, Agreement $agreement): ?AgreementParty
    {
        if ($user->id === $agreement->student_id) {
            return AgreementParty::Student;
        }

        if ($user->belongsToTeam($agreement->team)) {
            return AgreementParty::Client;
        }

        return null;
    }
}
