<?php

namespace App\Actions\Agreements;

use App\Enums\AgreementParty;
use App\Enums\ContractRequestStatus;
use App\Enums\ContractRequestType;
use App\Models\Addendum;
use App\Models\Agreement;
use App\Models\ContractRequest;
use App\Models\User;
use App\Notifications\Agreements\ContractRequestUpdated;
use App\Policies\AddendumPolicy;
use Illuminate\Support\Facades\Notification;

/**
 * One side asks the other to end a contract (owner, 2026-10-18).
 *
 * A denial turns down an MoA or an Addendum before both have signed; a
 * termination asks to end a running collaboration. Both need a reason, and
 * both wait for the other side's answer (AnswerContractRequest). A client
 * asking to terminate rates the work now, because completing is rating.
 * Who may ask is AgreementPolicy::deny / terminate and AddendumPolicy::deny.
 */
class AskToEndContract
{
    /**
     * Create a new action instance.
     */
    public function __construct(private readonly AddendumPolicy $parties) {}

    /**
     * Ask to deny an unsigned MoA or Addendum.
     */
    public function deny(Agreement|Addendum $subject, User $user, string $reason): ContractRequest
    {
        $agreement = $subject instanceof Addendum ? $subject->agreement : $subject;

        return $this->ask($subject, $agreement, $user, ContractRequestType::Denial, $reason);
    }

    /**
     * Ask to terminate a running collaboration.
     */
    public function terminate(Agreement $agreement, User $user, string $reason, ?int $rating, ?string $feedback): ContractRequest
    {
        return $this->ask($agreement, $agreement, $user, ContractRequestType::Termination, $reason, $rating, $feedback);
    }

    /**
     * Record the request and tell the side that has to answer it.
     */
    protected function ask(
        Agreement|Addendum $subject,
        Agreement $agreement,
        User $user,
        ContractRequestType $type,
        string $reason,
        ?int $rating = null,
        ?string $feedback = null,
    ): ContractRequest {
        $party = $this->parties->partyFor($user, $agreement) ?? AgreementParty::Student;

        $request = ContractRequest::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'type' => $type,
            'party' => $party,
            'requested_by' => $user->id,
            'reason' => $reason,
            /* Only the client rates. */
            'rating' => $party === AgreementParty::Client ? $rating : null,
            'feedback' => $party === AgreementParty::Client ? $feedback : null,
            'status' => ContractRequestStatus::Pending,
        ]);

        Notification::send(
            $party === AgreementParty::Client ? $agreement->studentSide() : $agreement->team->members,
            new ContractRequestUpdated($request, 'requested'),
        );

        return $request;
    }
}
