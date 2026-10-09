<?php

namespace App\Http\Controllers\Agreements;

use App\Actions\Agreements\PresentAddendum;
use App\Actions\Agreements\SettleAddendumPayment;
use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\AddendumPayment;
use App\Services\Payments\SimulatedPaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * SDPC's stand-in for PayMongo's hosted checkout, while no key is set.
 *
 * Says plainly that it is a test and that nothing is charged; confirming
 * clears the milestone through the same SettleAddendumPayment a real webhook
 * uses, with a pay_sim_ id. It 404s the moment PAYMONGO_SECRET_KEY is set.
 */
class SimulatedCheckoutController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * Show the test checkout for one milestone.
     */
    public function show(Request $request, AddendumPayment $payment): Response
    {
        $this->authorizeCheckout($payment);

        $addendum = $payment->addendum;
        $agreement = $addendum->agreement;

        return Inertia::render('agreements/simulated-checkout', [
            'payment' => [
                'label' => $payment->label(),
                'amountLabel' => PresentAddendum::peso($payment->amount),
                'invoiceNumber' => $payment->invoice_number,
                'reference' => $addendum->reference,
                'projectTitle' => $agreement->project->title,
                'confirmUrl' => route('payments.simulated.store', $payment),
                'cancelUrl' => $this->addendumUrl($payment),
            ],
        ]);
    }

    /**
     * Clear the milestone as a paid GCash checkout would.
     */
    public function store(Request $request, AddendumPayment $payment, SettleAddendumPayment $settleAddendumPayment): RedirectResponse
    {
        $this->authorizeCheckout($payment);

        $settleAddendumPayment->handle($payment, 'pay_sim_'.Str::lower(Str::random(24)), $this->gateway->name());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':milestone is paid (test checkout).', ['milestone' => $payment->label()]),
        ]);

        return redirect($this->addendumUrl($payment));
    }

    /**
     * Only while simulating, only for the paying client, only for a milestone due now.
     */
    protected function authorizeCheckout(AddendumPayment $payment): void
    {
        abort_unless($this->gateway instanceof SimulatedPaymentGateway, HttpResponse::HTTP_NOT_FOUND);

        Gate::authorize('pay', $payment->addendum);

        abort_unless($payment->addendum->isPayable($payment->milestone), HttpResponse::HTTP_CONFLICT, __('This milestone cannot be paid now.'));
    }

    /**
     * The addendum's screen, under the business the contract is with.
     */
    protected function addendumUrl(AddendumPayment $payment): string
    {
        $addendum = $payment->addendum;

        return route('agreements.addenda.show', [
            'current_team' => $addendum->agreement->team->slug,
            'agreement' => $addendum->agreement_id,
            'addendum' => $addendum->id,
        ]);
    }
}
