<?php

namespace App\Http\Controllers\Agreements;

use App\Actions\Agreements\SettleAddendumPayment;
use App\Contracts\PaymentGateway;
use App\Enums\AddendumPaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Addendum;
use App\Models\AddendumPayment;
use App\Models\Agreement;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The client pays an addendum milestone through the gateway's hosted checkout.
 *
 * checkout opens a session and sends the browser to it (PayMongo's page, or
 * SDPC's simulated one while no key is set); paid brings them back and asks
 * the gateway whether it cleared. The webhook settles the same row on its own,
 * so a client who closes the tab after paying is still recorded.
 */
class AddendumPaymentController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly SettleAddendumPayment $settleAddendumPayment,
    ) {}

    /**
     * Send the client to the checkout for one milestone.
     */
    public function checkout(Request $request, Team $currentTeam, Agreement $agreement, Addendum $addendum, AddendumPayment $payment): RedirectResponse|HttpResponse
    {
        $this->authorizePayment($agreement, $addendum, $payment);

        if (! $addendum->isPayable($payment->milestone)) {
            throw ValidationException::withMessages([
                'payment' => $payment->milestone === 2
                    ? __('The final balance is due once the down payment has cleared and the student has handed in every extended task.')
                    : __('This milestone cannot be paid now.'),
            ]);
        }

        $back = $this->addendumUrl($currentTeam, $agreement, $addendum);

        /* An earlier checkout may already have been paid: never open a second one. */
        if ($payment->checkout_session_id !== null && $this->settleIfPaid($payment)) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __(':milestone is paid.', ['milestone' => $payment->label()])]);

            return redirect($back);
        }

        try {
            $checkout = $this->gateway->createCheckout(
                $payment,
                __(':project · Addendum :reference', [
                    'project' => $agreement->project->title,
                    'reference' => $addendum->reference,
                ]),
                route('agreements.addenda.payments.paid', [
                    'current_team' => $currentTeam,
                    'agreement' => $agreement,
                    'addendum' => $addendum,
                    'payment' => $payment,
                ]),
                $back,
            );
        } catch (RuntimeException $exception) {
            Log::warning('Addendum checkout failed', ['payment' => $payment->id, 'error' => $exception->getMessage()]);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('The payment page could not be opened. Please try again in a moment.'),
            ]);

            return redirect($back);
        }

        $payment->update([
            'checkout_session_id' => $checkout['id'],
            'gateway' => $this->gateway->name(),
            'paid_by' => $request->user()->id,
        ]);

        /* A full page visit: the hosted checkout is another site. */
        return Inertia::location($checkout['url']);
    }

    /**
     * Where the gateway sends the client back after paying.
     */
    public function paid(Request $request, Team $currentTeam, Agreement $agreement, Addendum $addendum, AddendumPayment $payment): RedirectResponse
    {
        abort_unless(
            $addendum->agreement_id === $agreement->id && $payment->addendum_id === $addendum->id,
            HttpResponse::HTTP_NOT_FOUND,
        );

        /*
         * Read, not pay: the webhook may have completed the addendum before the
         * browser got back, and the client must still land on it.
         */
        Gate::authorize('view', $addendum);

        $back = redirect($this->addendumUrl($currentTeam, $agreement, $addendum));

        if ($payment->refresh()->status === AddendumPaymentStatus::Paid
            || ($payment->checkout_session_id !== null && $this->settleIfPaid($payment))) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __(':milestone is paid.', ['milestone' => $payment->label()])]);

            return $back;
        }

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => __('PayMongo is still confirming the payment. It will show here as paid as soon as it clears.'),
        ]);

        return $back;
    }

    /**
     * Ask the gateway about the milestone's checkout and settle it when it was paid.
     */
    protected function settleIfPaid(AddendumPayment $payment): bool
    {
        $paymentId = $this->gateway->paidPaymentId((string) $payment->checkout_session_id);

        if ($paymentId === null) {
            return false;
        }

        $this->settleAddendumPayment->handle($payment, $paymentId, $this->gateway->name());

        return true;
    }

    /**
     * Refuse a payment from another addendum, or anyone but the paying client.
     */
    protected function authorizePayment(Agreement $agreement, Addendum $addendum, AddendumPayment $payment): void
    {
        abort_unless(
            $addendum->agreement_id === $agreement->id && $payment->addendum_id === $addendum->id,
            HttpResponse::HTTP_NOT_FOUND,
        );

        Gate::authorize('pay', $addendum);
    }

    /**
     * The addendum's own screen.
     */
    protected function addendumUrl(Team $currentTeam, Agreement $agreement, Addendum $addendum): string
    {
        return route('agreements.addenda.show', [
            'current_team' => $currentTeam,
            'agreement' => $agreement,
            'addendum' => $addendum,
        ]);
    }
}
