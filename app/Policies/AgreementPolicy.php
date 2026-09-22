<?php

namespace App\Policies;

use App\Enums\AgreementParty;
use App\Enums\AgreementStatus;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Agreement;
use App\Models\Membership;
use App\Models\User;

class AgreementPolicy
{
    /**
     * Determine whether the user can read the agreement.
     *
     * The two parties, and — once the contract is signed — the teammates on
     * the signing student's team. They build what it describes, so they have
     * to be able to read the scope, the dates and the terms they are working
     * under; reading is all they get, because update/sign/requestChanges below
     * each ask for a party of their own.
     *
     * Active and Completed only: a draft still being negotiated is between the
     * two people whose names go on it, and a teammate who joined later has no
     * business in a version nobody signed. Beyond that there is no "anyone on
     * the platform" case here the way there is for a posting — an agreement
     * carries scope and signatures.
     */
    public function view(User $user, Agreement $agreement): bool
    {
        if ($this->partyFor($user, $agreement) !== null) {
            return true;
        }

        return in_array($agreement->status, [AgreementStatus::Active, AgreementStatus::Completed], true)
            && $this->isStudentsTeammate($user, $agreement);
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
     * Determine whether the user can open the agreement's Project Management.
     *
     * Only once the two sides are collaborating — both signatures in, so the
     * agreement is active. Before that there is no work to track and nothing
     * anybody agreed to track it against.
     *
     * Beyond the two parties, the signing student's own teammates: a capstone
     * group builds together.
     *
     * A completed agreement stays readable, as the record of what was
     * delivered. Nothing on it can be written any more — every write ability
     * below still requires Active.
     */
    public function viewProgress(User $user, Agreement $agreement): bool
    {
        if (! in_array($agreement->status, [AgreementStatus::Active, AgreementStatus::Completed], true)) {
            return false;
        }

        return $this->partyFor($user, $agreement) !== null
            || $this->isStudentsTeammate($user, $agreement);
    }

    /**
     * Determine whether the user can write the checklist and move the timeline.
     *
     * The student side: the student who signed and the teammates on their team,
     * who are tied to this build (User::isLockedToProject) and so share the
     * work of it — adding, editing, reordering and checking off tasks,
     * planning phase dates and asking for a deadline to move. A client is
     * read-only here by design: the side that verifies work must not also be
     * the side that defines and reports it.
     */
    public function manageTasks(User $user, Agreement $agreement): bool
    {
        return $agreement->status === AgreementStatus::Active
            && ($this->partyFor($user, $agreement) === AgreementParty::Student
                || $this->isStudentsTeammate($user, $agreement));
    }

    /**
     * Determine whether the user can verify a task, or send one back.
     *
     * The client, and only the client: this is the one move that makes
     * progress, so the student can never make it on their own work.
     */
    public function verifyTasks(User $user, Agreement $agreement): bool
    {
        return $agreement->status === AgreementStatus::Active
            && $this->partyFor($user, $agreement) === AgreementParty::Client;
    }

    /**
     * Determine whether the user can end the collaboration.
     *
     * The client accepts the turnover. Someone on the business who may manage
     * its projects, on an agreement still in flight — completing is final, so
     * it is not open to every member of the team.
     */
    public function complete(User $user, Agreement $agreement): bool
    {
        return $agreement->status === AgreementStatus::Active
            && $this->partyFor($user, $agreement) === AgreementParty::Client
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
