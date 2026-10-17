<?php

namespace App\Http\Controllers\Agreements;

use App\Actions\Agreements\AnswerContractRequest;
use App\Actions\Agreements\AskToEndContract;
use App\Enums\ContractRequestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agreements\AnswerContractRequestRequest;
use App\Http\Requests\Agreements\AskToEndContractRequest;
use App\Models\Addendum;
use App\Models\Agreement;
use App\Models\ContractRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Denials and termination requests (owner, 2026-10-18): one side asks with a
 * reason, the other side accepts or declines, and the side that asked may take
 * it back. AskToEndContract and AnswerContractRequest do the work; the
 * policies decide who may.
 */
class ContractRequestController extends Controller
{
    /**
     * Ask to deny an unsigned Memorandum of Agreement.
     */
    public function denyAgreement(AskToEndContractRequest $request, Team $currentTeam, Agreement $agreement, AskToEndContract $ask): RedirectResponse
    {
        Gate::authorize('deny', $agreement);

        $ask->deny($agreement, $request->user(), $request->string('reason')->trim()->toString());

        return $this->done(__('Denial sent. The agreement is cancelled once the other side accepts it.'));
    }

    /**
     * Ask to deny an unsigned Addendum.
     */
    public function denyAddendum(AskToEndContractRequest $request, Team $currentTeam, Agreement $agreement, Addendum $addendum, AskToEndContract $ask): RedirectResponse
    {
        abort_unless($addendum->agreement_id === $agreement->id, HttpResponse::HTTP_NOT_FOUND);

        Gate::authorize('deny', $addendum);

        $ask->deny($addendum, $request->user(), $request->string('reason')->trim()->toString());

        return $this->done(__('Denial sent. The extension is cancelled once the other side accepts it.'));
    }

    /**
     * Ask to end the running collaboration early.
     */
    public function terminate(AskToEndContractRequest $request, Team $currentTeam, Agreement $agreement, AskToEndContract $ask): RedirectResponse
    {
        Gate::authorize('terminate', $agreement);

        $ask->terminate(
            $agreement,
            $request->user(),
            $request->string('reason')->trim()->toString(),
            $request->filled('rating') ? $request->integer('rating') : null,
            $request->validated('feedback'),
        );

        return $this->done(__('Termination request sent. The collaboration ends once the other side agrees.'));
    }

    /**
     * Agree to the request.
     */
    public function accept(AnswerContractRequestRequest $request, Team $currentTeam, Agreement $agreement, ContractRequest $contractRequest, AnswerContractRequest $answer): RedirectResponse
    {
        $this->authorizeOn($agreement, $contractRequest, 'decide');

        $answer->accept(
            $contractRequest,
            $request->user(),
            $request->filled('rating') ? $request->integer('rating') : null,
            $request->validated('feedback'),
        );

        return $this->done(match (true) {
            $contractRequest->type === ContractRequestType::Termination => __('The collaboration has ended and the project is completed.'),
            $contractRequest->subject instanceof Addendum => __('Denial accepted. The extension is cancelled.'),
            default => __('Denial accepted. The agreement is cancelled, and both sides are free to start something new.'),
        });
    }

    /**
     * Say no to the request.
     */
    public function decline(AnswerContractRequestRequest $request, Team $currentTeam, Agreement $agreement, ContractRequest $contractRequest, AnswerContractRequest $answer): RedirectResponse
    {
        $this->authorizeOn($agreement, $contractRequest, 'decide');

        $answer->decline($contractRequest, $request->user(), $request->validated('note'));

        return $this->done(__('Request declined. Everything carries on as before.'));
    }

    /**
     * Take back a request you made.
     */
    public function withdraw(Request $request, Team $currentTeam, Agreement $agreement, ContractRequest $contractRequest, AnswerContractRequest $answer): RedirectResponse
    {
        $this->authorizeOn($agreement, $contractRequest, 'withdraw');

        $answer->withdraw($contractRequest, $request->user());

        return $this->done(__('Request withdrawn.'));
    }

    /**
     * Refuse a request from another agreement, or anyone the policy turns away.
     */
    protected function authorizeOn(Agreement $agreement, ContractRequest $contractRequest, string $ability): void
    {
        abort_unless($contractRequest->agreement()->id === $agreement->id, HttpResponse::HTTP_NOT_FOUND);

        Gate::authorize($ability, $contractRequest);
    }

    /**
     * Back to the screen the answer was given on, with a toast.
     */
    protected function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
