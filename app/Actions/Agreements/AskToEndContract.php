<?php

namespace App\Actions\Agreements;

use App\Enums\AddendumStatus;
use App\Enums\AgreementParty;
use App\Enums\AgreementStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ContractRequestStatus;
use App\Enums\ContractRequestType;
use App\Models\Addendum;
use App\Models\Agreement;
use App\Models\ContractRequest;
use App\Models\User;
use App\Notifications\Agreements\ContractRequestUpdated;
use App\Policies\AddendumPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * One side ends, or asks to end, a contract (owner, 2026-10-18).
 *
 * A denial turns down an MoA or an Addendum before both have signed, and is
 * final the moment it is sent: the contract is cancelled at once and the other
 * side can neither decline nor deny it back. A termination asks to end a
 * running collaboration and waits for the other side's answer
 * (AnswerContractRequest). Both need a reason. A client asking to terminate
 * rates the work now, because completing is rating. Who may ask is
 * AgreementPolicy::deny / terminate and AddendumPolicy::deny.
 */
class AskToEndContract
{
    /**
     * Create a new action instance.
     */
    public function __construct(private readonly AddendumPolicy $parties) {}

    /**
     * Deny an unsigned MoA or Addendum: it is cancelled at once, and both sides hear it.
     *
     * @throws ValidationException when the contract can no longer be denied
     */
    public function deny(Agreement|Addendum $subject, User $user, string $reason): ContractRequest
    {
        $agreement = $subject instanceof Addendum ? $subject->agreement : $subject;
        $party = $this->parties->partyFor($user, $agreement) ?? AgreementParty::Student;

        $request = DB::transaction(function () use ($subject, $user, $reason, $party): ContractRequest {
            /* Locked, so a signature cannot land between the check and the cancel. */
            $this->cancel($subject->newQuery()->lockForUpdate()->findOrFail($subject->getKey()), $party);

            return ContractRequest::query()->create([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'type' => ContractRequestType::Denial,
                'party' => $party,
                'requested_by' => $user->id,
                'reason' => $reason,
                'status' => ContractRequestStatus::Denied,
                'decided_by' => $user->id,
                'decided_at' => now(),
            ]);
        });

        Notification::send(
            $agreement->team->members->merge($agreement->studentSide()),
            new ContractRequestUpdated($request, 'denied'),
        );

        return $request;
    }

    /**
     * Ask to terminate a running collaboration.
     */
    public function terminate(Agreement $agreement, User $user, string $reason, ?int $rating, ?string $feedback): ContractRequest
    {
        return $this->ask($agreement, $agreement, $user, ContractRequestType::Termination, $reason, $rating, $feedback);
    }

    /**
     * Cancel the unsigned MoA or Addendum that was denied.
     *
     * @throws ValidationException when it is no longer awaiting signatures
     */
    protected function cancel(Agreement|Addendum $subject, AgreementParty $party): void
    {
        if ($subject instanceof Addendum) {
            if (! $subject->status->acceptsSignatures()) {
                throw ValidationException::withMessages(['request' => __('This addendum can no longer be denied.')]);
            }

            $subject->update(['status' => AddendumStatus::Cancelled, 'cancelled_at' => now()]);

            return;
        }

        if (! $subject->status->acceptsSignatures()) {
            throw ValidationException::withMessages(['request' => __('This agreement can no longer be denied.')]);
        }

        $subject->update(['status' => AgreementStatus::Cancelled]);

        /*
         * Undo the acceptance the agreement was drafted from: the student is
         * no longer committed to this posting (User::isLockedToProject reads
         * accepted applications), and the posting, still open, can take
         * someone else.
         */
        $subject->application?->update([
            'status' => $party === AgreementParty::Student
                ? ApplicationStatus::Withdrawn
                : ApplicationStatus::Rejected,
        ]);
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
