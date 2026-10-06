<?php

namespace App\Actions\Agreements;

use App\Enums\AgreementParty;
use App\Enums\AgreementStatus;
use App\Enums\AgreementTemplate;
use App\Enums\ProjectStatus;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\AgreementRequirement;
use App\Models\AgreementSignature;
use App\Models\User;
use App\Notifications\Agreements\AgreementSigned;
use App\Notifications\Client\ProjectStatusChanged;
use App\Support\TimelineWindow;
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
     * Create a new action instance.
     */
    public function __construct(private readonly SeedServiceTasks $seedServiceTasks) {}

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
        $reason = $this->reasonItCannotBeSigned($agreement);

        if ($reason !== null) {
            throw ValidationException::withMessages(['signed_name' => $reason]);
        }
    }

    /**
     * Say what still stops either party signing, or null when nothing does.
     *
     * The SDPC memorandum's Section VII (Description of Services) is required:
     * with no service in it, or a service without its objective or scope,
     * neither the client nor the student may sign. Every phase then needs an
     * end date, and Turnover both dates at least a month apart. The contract
     * screens read this too (PresentAgreement), so they say why before anyone
     * tries.
     */
    public function reasonItCannotBeSigned(Agreement $agreement): ?string
    {
        /* Read fresh, in position order: Turnover last. */
        $milestones = $agreement->milestones()->get();
        $turnover = $milestones->last();

        if ($agreement->template === AgreementTemplate::SdpcMemorandum) {
            $services = $agreement->services()->get();

            if ($services->isEmpty()) {
                return __('Section VII. Description of Services needs at least one service (an objective and its scope) before this agreement can be signed.');
            }

            if ($services->contains(fn (AgreementRequirement $service): bool => blank($service->title) || blank($service->body))) {
                return __('Every service in Section VII needs its objective and its scope before this agreement can be signed.');
            }

            /*
             * The timeline is Turnover's dates only: Objective & Scope carries
             * no deadline (owner, 2026-10-07).
             */
            if ($turnover !== null && ($turnover->starts_on === null || $turnover->ends_on === null)) {
                return __('Set the Turnover dates in the Timeline before this agreement can be signed.');
            }

            if ($turnover !== null && ! TimelineWindow::isLongEnoughForTurnover($turnover->starts_on, $turnover->ends_on)) {
                return TimelineWindow::turnoverTooShortMessage($turnover->starts_on);
            }

            return null;
        }

        if ($milestones->contains(fn (AgreementMilestone $milestone): bool => $milestone->ends_on === null)) {
            return __('Every milestone needs an end date before this agreement can be signed.');
        }

        return null;
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
     *
     * The Section VII services go into Project Management here, once: each
     * service one task in Objective & Scope, the objective its title and the
     * scope its description (SeedServiceTasks).
     */
    protected function activate(Agreement $agreement): void
    {
        $agreement->update([
            'status' => AgreementStatus::Active,
            'activated_at' => now(),
        ]);

        $this->seedServiceTasks->handle($agreement);

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
