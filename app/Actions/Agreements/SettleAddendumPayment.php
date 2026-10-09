<?php

namespace App\Actions\Agreements;

use App\Enums\AddendumPaymentStatus;
use App\Enums\AddendumStatus;
use App\Enums\TaskStatus;
use App\Models\Addendum;
use App\Models\AddendumPayment;
use App\Notifications\Agreements\AddendumPaymentCleared;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Marks an addendum milestone paid, from whichever signal arrives first.
 *
 * Three doors lead here and all of them are safe to repeat: PayMongo's
 * checkout_session.payment.paid webhook, the client's return from PayMongo
 * (which asks for the session), and the simulated checkout. The row is locked
 * and a paid one is left alone, so a webhook landing after the redirect does
 * nothing twice.
 *
 * Milestone 1 starts the extended work (Section III.2): each Section II
 * service becomes one task in Project Management's Objective & Scope, marked
 * with the addendum so its proof stays locked from the client. Milestone 2
 * completes the addendum, which is what unlocks those files (Section VI).
 */
class SettleAddendumPayment
{
    /**
     * Create a new action instance.
     */
    public function __construct(private readonly SyncPhaseStatus $syncPhaseStatus) {}

    /**
     * Settle the milestone. Returns false when it was already paid.
     */
    public function handle(AddendumPayment $payment, string $providerPaymentId, string $gateway): bool
    {
        $settled = DB::transaction(function () use ($payment, $providerPaymentId, $gateway): bool {
            $locked = AddendumPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status === AddendumPaymentStatus::Paid) {
                return false;
            }

            $locked->update([
                'status' => AddendumPaymentStatus::Paid,
                'provider_payment_id' => $providerPaymentId,
                'gateway' => $gateway,
                'payment_method' => 'gcash',
                'paid_at' => now(),
            ]);

            $addendum = $locked->addendum;

            if ($locked->milestone === 1) {
                $this->startTheWork($addendum);
            } else {
                $addendum->update([
                    'status' => AddendumStatus::Completed,
                    'completed_at' => now(),
                ]);
            }

            return true;
        });

        if ($settled) {
            $payment->refresh();
            $agreement = $payment->addendum->agreement;

            Notification::send(
                $agreement->team->members->merge($agreement->studentSide())->unique('id'),
                new AddendumPaymentCleared($payment),
            );
        }

        return $settled;
    }

    /**
     * Put Section II's services into Objective & Scope, one task each.
     *
     * The first phase by position, the same one the memorandum's Section VII
     * filled (SeedServiceTasks). Adding work reopens a phase already approved.
     */
    protected function startTheWork(Addendum $addendum): void
    {
        $phase = $addendum->agreement->milestones()->first();

        if ($phase === null) {
            return;
        }

        $position = (int) $phase->tasks()->max('position');

        foreach ($addendum->services()->get() as $service) {
            $phase->tasks()->create([
                'position' => ++$position,
                'title' => $service->objective,
                'description' => $service->scope,
                'status' => TaskStatus::Open,
                'addendum_id' => $addendum->id,
            ]);
        }

        $this->syncPhaseStatus->handle($phase);
    }
}
