<?php

namespace App\Http\Controllers\Agreements;

use App\Actions\Agreements\SettleAddendumPayment;
use App\Http\Controllers\Controller;
use App\Models\AddendumPayment;
use App\Services\Payments\PayMongoSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * PayMongo's server-to-server notice that a checkout was paid (Section V.2).
 *
 * The signature is checked against the raw body before anything is read; a
 * request that fails it is not from PayMongo and is refused. A paid
 * checkout_session settles the milestone whose session it is, which is safe
 * to repeat — PayMongo retries, and the client's return may have settled it
 * first. Anything else is acknowledged and ignored, so PayMongo stops sending
 * it.
 */
class PayMongoWebhookController extends Controller
{
    /**
     * Handle one webhook event.
     */
    public function __invoke(Request $request, SettleAddendumPayment $settleAddendumPayment): JsonResponse
    {
        $secret = (string) config('services.paymongo.webhook_secret');

        abort_if($secret === '', HttpResponse::HTTP_NOT_FOUND);

        abort_unless(
            PayMongoSignature::isValid($request->getContent(), $request->header('Paymongo-Signature'), $secret),
            HttpResponse::HTTP_BAD_REQUEST,
            'Invalid signature.',
        );

        if ($request->input('data.attributes.type') !== 'checkout_session.payment.paid') {
            return response()->json(['received' => true]);
        }

        $sessionId = $request->input('data.attributes.data.id');
        $paymentId = $request->input('data.attributes.data.attributes.payments.0.id');

        $payment = is_string($sessionId)
            ? AddendumPayment::query()->where('checkout_session_id', $sessionId)->first()
            : null;

        if ($payment !== null) {
            $settleAddendumPayment->handle($payment, is_string($paymentId) ? $paymentId : $sessionId, 'paymongo');
        }

        return response()->json(['received' => true]);
    }
}
