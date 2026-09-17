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
        $this->assertEveryAcknowledgementIsTicked($agreement, $acknowledgements);

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
     * Refuse to sign a schedule nobody has actually settled.
     *
     * A milestone with no deadline is a blank in the "Deliverables & schedule"
     * table, and a signature against a blank is worth nothing. The client sets
     * the dates before either side can put their name to it.
     *
     * No amount is asked for. The Memorandum of Agreement leaves payment for
     * the client and the team to agree between themselves, and the screens
     * stopped asking for prices — requiring one here meant no agreement drawn
     * up on the site could be signed at all.
     *
     * @throws ValidationException
     */
    protected function assertTermsAreComplete(Agreement $agreement): void
    {
        $undated = $agreement->milestones()->whereNull('ends_on')->exists();

        if ($undated) {
            throw ValidationException::withMessages([
                'signed_name' => __('Every milestone needs an end date before this agreement can be signed.'),
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
    protected function assertEveryAcknowledgementIsTicked(Agreement $agreement, array $acknowledgements): void
    {
        /* The statements that go with the wording this agreement is in. */
        $required = $agreement->template->acknowledgements();

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
