<?php

namespace App\Actions\Agreements;

use App\Enums\AgreementParty;
use App\Enums\AgreementStatus;
use App\Enums\ProjectStatus;
use App\Models\Agreement;
use App\Models\AgreementSignature;
use App\Models\User;
use App\Notifications\Agreements\AgreementSigned;
use App\Notifications\Client\ProjectStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Records one party's signature, and starts the work when the second lands.
 *
 * This is where "collaboration begins" actually happens. Accepting a student
 * used to move the posting straight into progress, which meant the terms were
 * a formality attached to work already under way. Now acceptance drafts the
 * contract and the second signature starts it — the order the vision document
 * describes.
 */
class SignAgreement
{
    /**
     * Sign the agreement on behalf of one party.
     *
     * @param  list<string>  $acknowledgements
     *
     * @throws ValidationException
     */
    public function handle(
        Agreement $agreement,
        User $signatory,
        AgreementParty $party,
        string $signedName,
        array $acknowledgements,
        ?Request $request = null,
    ): Agreement {
        $this->assertTermsAreComplete($agreement);
        $this->assertEveryAcknowledgementIsTicked($acknowledgements);

        return DB::transaction(function () use (
            $agreement, $signatory, $party, $signedName, $acknowledgements, $request
        ): Agreement {
            AgreementSignature::query()->create([
                'agreement_id' => $agreement->id,
                'user_id' => $signatory->id,
                'party' => $party,
                'signed_name' => $signedName,
                'acknowledgements' => $acknowledgements,
                'signed_at' => now(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);

            $agreement->load('signatures');

            if ($agreement->isFullySigned()) {
                $this->activate($agreement);
            } else {
                $agreement->update(['status' => AgreementStatus::AwaitingSignatures]);

                $this->notifyCounterparty($agreement, $party);
            }

            return $agreement->refresh()->load(['signatures', 'milestones']);
        });
    }

    /**
     * Refuse to sign terms nobody has actually settled.
     *
     * A milestone with no price and no dates is a blank in a contract, and a
     * signature against a blank is worth nothing. The client fills the figures
     * in before either side can put their name to it.
     *
     * @throws ValidationException
     */
    protected function assertTermsAreComplete(Agreement $agreement): void
    {
        $unpriced = $agreement->milestones()
            ->where(fn ($query) => $query->where('amount', 0)->orWhereNull('ends_on'))
            ->exists();

        if ($unpriced) {
            throw ValidationException::withMessages([
                'signed_name' => __('Every milestone needs an amount and an end date before this agreement can be signed.'),
            ]);
        }
    }

    /**
     * Refuse a partial acknowledgement.
     *
     * @param  list<string>  $acknowledgements
     *
     * @throws ValidationException
     */
    protected function assertEveryAcknowledgementIsTicked(array $acknowledgements): void
    {
        /** @var array<string, string> $required */
        $required = config('agreements.acknowledgements', []);

        $missing = array_diff(array_keys($required), $acknowledgements);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'acknowledgements' => __('Please confirm every statement before signing.'),
            ]);
        }
    }

    /**
     * Put the agreement into force and start the project.
     */
    protected function activate(Agreement $agreement): void
    {
        $agreement->update([
            'status' => AgreementStatus::Active,
            'activated_at' => now(),
        ]);

        $project = $agreement->project;

        if ($project->status === ProjectStatus::InProgress) {
            return;
        }

        $previousStatus = $project->status;

        $project->update(['status' => ProjectStatus::InProgress]);

        Notification::send(
            $project->team->members,
            new ProjectStatusChanged($project->refresh(), $previousStatus),
        );
    }

    /**
     * Tell the other side the agreement is waiting on them.
     */
    protected function notifyCounterparty(Agreement $agreement, AgreementParty $party): void
    {
        $counterparty = $party->counterparty();

        if ($counterparty === AgreementParty::Student) {
            $agreement->student->notify(new AgreementSigned($agreement, $party));

            return;
        }

        Notification::send(
            $agreement->team->members,
            new AgreementSigned($agreement, $party),
        );
    }
}
