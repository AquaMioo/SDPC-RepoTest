<?php

namespace App\Http\Controllers\Agreements;

use App\Actions\Agreements\PresentAddendum;
use App\Actions\Agreements\RequestProjectExtension;
use App\Enums\AddendumStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agreements\UpdateAddendumRequest;
use App\Models\Addendum;
use App\Models\Agreement;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The Payment & Project Extension Addendum, beside the memorandum it extends.
 *
 * The client opens one from Project Management's "Project extension" button
 * (store); both parties then read and fill it here (show), print it filled in
 * (printable), download the blank form (template), and print the record of
 * its two payments (records). AddendumPolicy decides who may do what.
 */
class AddendumController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private readonly PresentAddendum $presentAddendum) {}

    /**
     * Ask for an extension: open a draft addendum and go to it.
     */
    public function store(Request $request, Team $currentTeam, Agreement $agreement, RequestProjectExtension $requestProjectExtension): RedirectResponse
    {
        Gate::authorize('create', [Addendum::class, $agreement]);

        $addendum = $requestProjectExtension->handle($agreement, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Addendum :reference opened. The student has been told.', ['reference' => $addendum->reference])]);

        return to_route('agreements.addenda.show', [
            'current_team' => $currentTeam,
            'agreement' => $agreement,
            'addendum' => $addendum,
        ]);
    }

    /**
     * Show the addendum to fill in, sign and pay.
     */
    public function show(Request $request, Team $currentTeam, Agreement $agreement, Addendum $addendum): Response
    {
        $this->authorizeView($agreement, $addendum);

        return Inertia::render('agreements/addendum', [
            'addendum' => $this->presentAddendum->handle($addendum, $request->user()),
        ]);
    }

    /**
     * Show the addendum filled in, laid out to print or save as a PDF, signed or not.
     */
    public function printable(Request $request, Team $currentTeam, Agreement $agreement, Addendum $addendum): Response
    {
        $this->authorizeView($agreement, $addendum);

        return Inertia::render('agreements/addendum-printable', [
            'addendum' => $this->presentAddendum->handle($addendum, $request->user()),
        ]);
    }

    /**
     * Show the record of the addendum's transactions, laid out to print or save.
     */
    public function records(Request $request, Team $currentTeam, Agreement $agreement, Addendum $addendum): Response
    {
        $this->authorizeView($agreement, $addendum);

        return Inertia::render('agreements/addendum-records', [
            'addendum' => $this->presentAddendum->handle($addendum, $request->user()),
            'generatedAt' => now()->format('F j, Y, g:i a'),
        ]);
    }

    /**
     * Download the blank addendum form (SDPC_Addendum.pdf), unsigned.
     */
    public function template(Request $request, Team $currentTeam, Agreement $agreement, Addendum $addendum): BinaryFileResponse
    {
        $this->authorizeView($agreement, $addendum);

        return response()->download(
            resource_path('documents/sdpc-addendum.pdf'),
            "{$addendum->reference} Addendum.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Set Section IV's target amount.
     */
    public function update(UpdateAddendumRequest $request, Team $currentTeam, Agreement $agreement, Addendum $addendum): RedirectResponse
    {
        abort_unless($addendum->agreement_id === $agreement->id, HttpResponse::HTTP_NOT_FOUND);

        $addendum->update(['total_amount' => $request->integer('total_amount')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Target amount saved.')]);

        return back();
    }

    /**
     * Withdraw the extension before both sides have signed.
     */
    public function destroy(Request $request, Team $currentTeam, Agreement $agreement, Addendum $addendum): RedirectResponse
    {
        abort_unless($addendum->agreement_id === $agreement->id, HttpResponse::HTTP_NOT_FOUND);

        Gate::authorize('cancel', $addendum);

        $addendum->update([
            'status' => AddendumStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The project extension was cancelled. The memorandum is unchanged.')]);

        return to_route('agreements.show', [
            'current_team' => $currentTeam,
            'agreement' => $agreement,
        ]);
    }

    /**
     * Refuse an addendum from another agreement, or one the user may not read.
     */
    protected function authorizeView(Agreement $agreement, Addendum $addendum): void
    {
        abort_unless($addendum->agreement_id === $agreement->id, HttpResponse::HTTP_NOT_FOUND);

        Gate::authorize('view', $addendum);
    }
}
