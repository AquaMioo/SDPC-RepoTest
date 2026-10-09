<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\AddendumPayment;
use Illuminate\Support\Str;

/**
 * The checkout SDPC uses while no PayMongo key is configured.
 *
 * Its "hosted page" is SDPC's own (SimulatedCheckoutController), clearly
 * labelled a test: the client confirms and the milestone clears with a
 * pay_sim_ id, exactly as a webhook would clear it. Nothing is charged and no
 * wallet is asked for. Swapped out the moment PAYMONGO_SECRET_KEY is set.
 */
class SimulatedPaymentGateway implements PaymentGateway
{
    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'simulated';
    }

    /**
     * {@inheritDoc}
     */
    public function createCheckout(AddendumPayment $payment, string $description, string $successUrl, string $cancelUrl): array
    {
        return [
            'id' => 'cs_sim_'.Str::lower(Str::random(24)),
            'url' => route('payments.simulated.show', $payment),
        ];
    }

    /**
     * {@inheritDoc}
     *
     * Only the simulated page clears a simulated session, and it settles the
     * payment itself; there is never anything to look up.
     */
    public function paidPaymentId(string $checkoutSessionId): ?string
    {
        return null;
    }
}
