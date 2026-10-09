<?php

namespace App\Actions\Agreements;

use App\Enums\AddendumPaymentStatus;
use App\Enums\AddendumStatus;
use App\Enums\AgreementParty;
use App\Models\Addendum;
use App\Models\AddendumService;
use App\Models\User;
use App\Notifications\Agreements\AddendumExecuted;
use App\Notifications\Agreements\AddendumSigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Records one party's signature on the addendum, and executes it when the
 * second lands.
 *
 * Nobody signs until Section II has a service, Section IV has its target
 * amount and BOTH parties have a GCash account in Settings (owner,
 * 2026-10-10). Each signature keeps its party's GCash account as it stood at
 * that moment, so the signed copy never changes when a profile does. The
 * second signature writes Section IV's two milestones; the down payment is due
 * from then on.
 */
class SignAddendum
{
    /**
     * Sign the addendum on behalf of one party.
     *
     * @throws ValidationException
     */
    public function handle(Addendum $addendum, User $signatory, AgreementParty $party, string $signedName): Addendum
    {
        $reason = $this->reasonItCannotBeSigned($addendum);

        if ($reason !== null) {
            throw ValidationException::withMessages(['signed_name' => $reason]);
        }

        $executed = DB::transaction(function () use ($addendum, $signatory, $party, $signedName): bool {
            /* Locked, so the two sides signing at once cannot both miss the other. */
            $locked = Addendum::query()->lockForUpdate()->findOrFail($addendum->id);

            if (! $locked->status->acceptsSignatures() || $locked->isSignedBy($party)) {
                throw ValidationException::withMessages([
                    'signed_name' => __('This addendum can no longer be signed by your side.'),
                ]);
            }

            $side = $party === AgreementParty::Client ? 'client' : 'student';
            $representative = $party === AgreementParty::Client
                ? $addendum->clientRepresentative()
                : $addendum->studentRepresentative();

            $locked->forceFill([
                "{$side}_signed_by" => $signatory->id,
                "{$side}_signed_name" => $signedName,
                "{$side}_signed_at" => now(),
                "{$side}_gcash_number" => $representative?->gcash_number,
            ]);

            $executed = $locked->client_signed_at !== null && $locked->student_signed_at !== null;

            $locked->status = $executed ? AddendumStatus::Active : AddendumStatus::AwaitingSignatures;

            if ($executed) {
                $locked->executed_at = now();
            }

            $locked->save();

            if ($executed) {
                $this->writeMilestones($locked);
            }

            return $executed;
        });

        $addendum->refresh();

        $this->announce($addendum, $party, $executed);

        return $addendum;
    }

    /**
     * Say what still stops either party signing, or null when nothing does.
     *
     * The addendum screens read this too (PresentAddendum), so they say why
     * before anyone tries.
     */
    public function reasonItCannotBeSigned(Addendum $addendum): ?string
    {
        $services = $addendum->services()->get();

        if ($services->isEmpty()) {
            return __('Section II. Purpose & Scope of Extension needs at least one service (an objective and its scope) before this addendum can be signed.');
        }

        if ($services->contains(fn (AddendumService $service): bool => blank($service->objective) || blank($service->scope))) {
            return __('Every service in Section II needs its objective and its scope before this addendum can be signed.');
        }

        $amount = $addendum->total_amount;

        if ($amount === null || $amount < Addendum::MIN_AMOUNT || $amount > Addendum::MAX_AMOUNT) {
            return __('Set Section IV’s target amount (₱:min to ₱:max) before this addendum can be signed.', [
                'min' => number_format(Addendum::MIN_AMOUNT),
                'max' => number_format(Addendum::MAX_AMOUNT),
            ]);
        }

        if (! $addendum->studentRepresentative()->hasGcashAccount()) {
            return __('The student has not registered a GCash account in Settings yet. Both parties need one before this addendum can be signed.');
        }

        if (! ($addendum->clientRepresentative()?->hasGcashAccount() ?? false)) {
            return __('The client has not registered a GCash account in Settings yet. Both parties need one before this addendum can be signed.');
        }

        return null;
    }

    /**
     * Write Section IV's two milestones from the target amount.
     */
    protected function writeMilestones(Addendum $addendum): void
    {
        foreach ($addendum->milestoneAmounts() as $milestone => $amount) {
            $addendum->payments()->create([
                'milestone' => $milestone,
                'percentage' => $milestone === 1 ? Addendum::DOWN_PAYMENT_PERCENT : 100 - Addendum::DOWN_PAYMENT_PERCENT,
                'amount' => $amount,
                'status' => AddendumPaymentStatus::Pending,
                'invoice_number' => 'INV-'.$addendum->reference.'-M'.$milestone,
            ]);
        }
    }

    /**
     * Tell the other side it is their turn, or both sides that it is signed.
     */
    protected function announce(Addendum $addendum, AgreementParty $party, bool $executed): void
    {
        $agreement = $addendum->agreement;

        if ($executed) {
            Notification::send(
                $agreement->team->members->merge($agreement->studentSide())->unique('id'),
                new AddendumExecuted($addendum),
            );

            return;
        }

        Notification::send(
            $party === AgreementParty::Client ? $agreement->studentSide() : $agreement->team->members,
            new AddendumSigned($addendum, $party),
        );
    }
}
