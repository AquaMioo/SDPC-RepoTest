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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The other side's answer to a denial or a termination request (owner,
 * 2026-10-18).
 *
 * Accepting a denial ends the pre-collaboration state at once. For an MoA the
 * agreement is cancelled and the student's acceptance is undone (withdrawn
 * when the student denied, rejected when the client did), which frees the
 * student to apply elsewhere and leaves the posting open for the client to
 * hire again. For an Addendum the extension is cancelled and the build
 * carries on.
 *
 * Accepting a termination cancels any open extension and completes the
 * project through CompleteProject, with the client's rating, so it counts like
 * any finished build. Declining or withdrawing changes nothing else.
 */
class AnswerContractRequest
{
    /**
     * Create a new action instance.
     */
    public function __construct(private readonly CompleteProject $completeProject) {}

    /**
     * Agree to the request and carry it out.
     *
     * @throws ValidationException when the contract can no longer be ended this way
     */
    public function accept(ContractRequest $request, User $decider, ?int $rating = null, ?string $feedback = null): void
    {
        DB::transaction(function () use ($request, $decider, $rating, $feedback): void {
            $locked = ContractRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($locked->status !== ContractRequestStatus::Pending) {
                throw ValidationException::withMessages(['request' => __('This request has already been answered.')]);
            }

            /* The client answering a termination rates the work as they agree. */
            $clientIsAnswering = $locked->party === AgreementParty::Student;

            $locked->update([
                'status' => ContractRequestStatus::Accepted,
                'decided_by' => $decider->id,
                'decided_at' => now(),
                'rating' => $clientIsAnswering && $locked->type === ContractRequestType::Termination ? $rating : $locked->rating,
                'feedback' => $clientIsAnswering && $locked->type === ContractRequestType::Termination ? $feedback : $locked->feedback,
            ]);

            match ($locked->type) {
                ContractRequestType::Denial => $this->cancel($locked),
                ContractRequestType::Termination => $this->terminate($locked, $decider),
            };
        });

        $this->announce($request->refresh(), 'accepted');
    }

    /**
     * Say no: the contract carries on.
     */
    public function decline(ContractRequest $request, User $decider, ?string $note = null): void
    {
        $request->update([
            'status' => ContractRequestStatus::Declined,
            'decided_by' => $decider->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        $this->announce($request, 'declined');
    }

    /**
     * Take the request back.
     */
    public function withdraw(ContractRequest $request, User $requester): void
    {
        $request->update([
            'status' => ContractRequestStatus::Withdrawn,
            'decided_by' => $requester->id,
            'decided_at' => now(),
        ]);

        $this->announce($request, 'withdrawn');
    }

    /**
     * Cancel the unsigned MoA or Addendum that was denied.
     */
    protected function cancel(ContractRequest $request): void
    {
        $subject = $request->subject;

        if ($subject instanceof Addendum) {
            if (! $subject->status->acceptsSignatures()) {
                throw ValidationException::withMessages(['request' => __('This addendum can no longer be denied.')]);
            }

            $subject->update(['status' => AddendumStatus::Cancelled, 'cancelled_at' => now()]);

            return;
        }

        /** @var Agreement $subject */
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
            'status' => $request->party === AgreementParty::Student
                ? ApplicationStatus::Withdrawn
                : ApplicationStatus::Rejected,
        ]);
    }

    /**
     * End the running collaboration: close any open extension, then complete.
     */
    protected function terminate(ContractRequest $request, User $decider): void
    {
        /** @var Agreement $agreement */
        $agreement = $request->subject;

        if ($agreement->status !== AgreementStatus::Active) {
            throw ValidationException::withMessages(['request' => __('Only a running collaboration can be terminated.')]);
        }

        Addendum::query()
            ->where('agreement_id', $agreement->id)
            ->open()
            ->update(['status' => AddendumStatus::Cancelled->value, 'cancelled_at' => now()]);

        if ($request->rating === null || $request->rating < 1 || $request->rating > 5) {
            throw ValidationException::withMessages(['rating' => __('The client rates the work (1 to 5 stars) to end the collaboration.')]);
        }

        $rater = $request->party === AgreementParty::Client ? ($request->requester ?? $decider) : $decider;

        $this->completeProject->handle($agreement, (int) $request->rating, $request->feedback, $rater);
    }

    /**
     * Tell the side that did not act.
     */
    protected function announce(ContractRequest $request, string $event): void
    {
        $agreement = $request->agreement();

        /* "accepted"/"declined" go back to the side that asked; "withdrawn" to the side that was asked. */
        $toRequesterSide = $event !== 'withdrawn';
        $side = $toRequesterSide ? $request->party : $request->party->counterparty();

        Notification::send(
            $side === AgreementParty::Client ? $agreement->team->members : $agreement->studentSide(),
            new ContractRequestUpdated($request, $event),
        );
    }
}
