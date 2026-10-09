<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\AddendumPayment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * PayMongo's Checkout API over plain HTTP: no gateway package
 * (.ai/rules/config.md). The secret key authenticates as the Basic user.
 *
 * The client pays on PayMongo's hosted page with GCash; nothing about their
 * wallet passes through SDPC. Clearance comes back two ways, both settling the
 * same row: the checkout_session.payment.paid webhook (PayMongoWebhookController)
 * and the success redirect, which asks for the session (paidPaymentId).
 */
class PayMongoGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $baseUrl,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'paymongo';
    }

    /**
     * {@inheritDoc}
     */
    public function createCheckout(AddendumPayment $payment, string $description, string $successUrl, string $cancelUrl): array
    {
        try {
            $response = $this->request()->post('/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'line_items' => [[
                            'name' => $payment->label(),
                            'amount' => $payment->amount,
                            'currency' => 'PHP',
                            'quantity' => 1,
                            'description' => mb_substr($description, 0, 255),
                        ]],
                        'payment_method_types' => ['gcash'],
                        'description' => mb_substr($description, 0, 255),
                        'reference_number' => $payment->invoice_number,
                        'success_url' => $successUrl,
                        'cancel_url' => $cancelUrl,
                        'send_email_receipt' => false,
                        'show_line_items' => true,
                        'metadata' => [
                            'addendum_payment_id' => (string) $payment->id,
                            'invoice_number' => $payment->invoice_number,
                        ],
                    ],
                ],
            ])->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new RuntimeException('PayMongo could not open the checkout: '.$exception->getMessage(), previous: $exception);
        }

        $id = $response->json('data.id');
        $url = $response->json('data.attributes.checkout_url');

        if (! is_string($id) || ! is_string($url)) {
            throw new RuntimeException('PayMongo answered without a checkout session.');
        }

        return ['id' => $id, 'url' => $url];
    }

    /**
     * {@inheritDoc}
     */
    public function paidPaymentId(string $checkoutSessionId): ?string
    {
        try {
            $session = $this->request()->get('/checkout_sessions/'.rawurlencode($checkoutSessionId))->throw();
        } catch (ConnectionException|RequestException) {
            return null;
        }

        foreach ((array) $session->json('data.attributes.payments', []) as $paid) {
            if (($paid['attributes']['status'] ?? null) === 'paid' && is_string($paid['id'] ?? null)) {
                return $paid['id'];
            }
        }

        return null;
    }

    /**
     * Start an authenticated JSON request to the API.
     */
    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }
}
