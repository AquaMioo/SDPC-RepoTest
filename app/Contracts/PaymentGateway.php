<?php

namespace App\Contracts;

use App\Models\AddendumPayment;
use RuntimeException;

/**
 * Where the client pays an addendum milestone.
 *
 * PayMongoGateway when PAYMONGO_SECRET_KEY is set (test or live keys), and
 * SimulatedPaymentGateway otherwise: SDPC's own checkout page that clears the
 * milestone without charging anybody, so the flow can be shown before an
 * account exists. AppServiceProvider picks one.
 */
interface PaymentGateway
{
    /**
     * The name recorded on the payment: "paymongo" or "simulated".
     */
    public function name(): string;

    /**
     * Open a hosted checkout for the milestone, payable by GCash.
     *
     * @return array{id: string, url: string} the checkout session id and the page to send the client to
     *
     * @throws RuntimeException when the gateway refuses
     */
    public function createCheckout(AddendumPayment $payment, string $description, string $successUrl, string $cancelUrl): array;

    /**
     * The gateway's payment id (pay_…) once the session has been paid, else null.
     */
    public function paidPaymentId(string $checkoutSessionId): ?string;
}
