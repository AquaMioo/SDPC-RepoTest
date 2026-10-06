<?php

namespace App\Http\Controllers\Agreements;

use App\Actions\Agreements\PresentAgreement;
use App\Enums\AgreementParty;
use App\Enums\AgreementStatus;
use App\Enums\AgreementTemplate;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agreements\SaveAgreementRequest;
use App\Models\Agreement;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The Terms & Agreement screen, shared by both sides.
 *
 * Deliberately not split into a client controller and a student controller:
 * there is one contract, both parties read the same document, and duplicating
 * it would be the fastest way to have the two views disagree about what was
 * signed. Who may do what is answered by AgreementPolicy.
 */
class AgreementController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private PresentAgreement $presentAgreement) {}

    /**
     * Send the user to their agreement, or list them if there is more than one.
     *
     * Both caps in the platform — one posting per business, one build per
     * student — mean a single standing agreement is the normal case, so the
     * nav link goes straight to the document rather than to a list of one.
     */
    public function index(Request $request, Team $currentTeam): RedirectResponse|Response
    {
        $user = $request->user();

        $agreements = $this->visibleTo($user)
            ->with(['project.team.clientProfile', 'student'])
            ->latest('id')
            ->get();

        $standing = $agreements->filter(fn (Agreement $agreement): bool => ! $agreement->status->isFinal());

        if ($standing->count() === 1) {
            return redirect()->route('agreements.show', [
                'current_team' => $currentTeam,
                'agreement' => $standing->first(),
            ]);
        }

        return Inertia::render('agreements/index', [
            'agreements' => $agreements
                ->map(fn (Agreement $agreement): array => [
                    'id' => $agreement->id,
                    'reference' => $agreement->reference,
                    'version' => $agreement->version,
                    'status' => $agreement->status->value,
                    'statusLabel' => $agreement->status->label(),
                    'statusVariant' => $agreement->status->tagVariant(),
                    'projectTitle' => $agreement->project->title,
                    /*
                     * Who the reader is looking at across the table. Decided
                     * by the side they sit on, not by whether they signed:
                     * a teammate reads the signer's contract and the business
                     * is still the other party for them.
                     */
                    'counterparty' => $user->belongsToTeam($agreement->project->team)
                        ? $agreement->student->name
                        : ($agreement->project->team->clientProfile?->business_name
                            ?? $agreement->project->team->name),
                    'totalAmount' => $agreement->total_amount,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Show the agreement.
     */
    public function show(Request $request, Team $currentTeam, Agreement $agreement): Response
    {
        Gate::authorize('view', $agreement);

        return Inertia::render('agreements/show', [
            'agreement' => $this->presentAgreement->handle(
                $agreement,
                $request->user(),
                $this->partyFor($request->user(), $agreement),
            ),
        ]);
    }

    /**
     * Show the full contract text.
     *
     * The same document as show(), read rather than acted on — the mockup's
     * "Read full contract" link.
     */
    public function contract(Request $request, Team $currentTeam, Agreement $agreement): Response
    {
        Gate::authorize('view', $agreement);

        return Inertia::render('agreements/contract', [
            'agreement' => $this->presentAgreement->handle(
                $agreement,
                $request->user(),
                $this->partyFor($request->user(), $agreement),
            ),
        ]);
    }

    /**
     * Download the school's Memorandum of Agreement as a PDF.
     *
     * The blank paper form, exactly as the school issues it: the template as
     * it reads before either side adds anything. Named after the agreement so
     * the copy is easy to find later. Each memorandum wording has its own
     * form (AgreementTemplate::blankForm); the earlier clauses have none.
     */
    public function memorandum(Request $request, Team $currentTeam, Agreement $agreement): BinaryFileResponse
    {
        Gate::authorize('view', $agreement);

        $form = $agreement->template->blankForm();

        abort_if($form === null, HttpResponse::HTTP_NOT_FOUND);

        return response()->download(
            $form,
            "{$agreement->reference} Memorandum of Agreement.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Show the SDPC memorandum filled in, laid out to print and sign.
     *
     * The finished document beside the blank form above: every blank filled
     * from the agreement and every party's addition in place, from the same
     * payload the contract screen edits, so the paper says exactly what the
     * screen does. The browser prints it, or saves it as a PDF; no PDF package
     * is needed (see .ai/rules/controllers-agreements.md).
     */
    public function printable(Request $request, Team $currentTeam, Agreement $agreement): Response
    {
        Gate::authorize('view', $agreement);

        abort_unless($agreement->template === AgreementTemplate::SdpcMemorandum, HttpResponse::HTTP_NOT_FOUND);

        return Inertia::render('agreements/printable', [
            'agreement' => $this->presentAgreement->handle(
                $agreement,
                $request->user(),
                $this->partyFor($request->user(), $agreement),
            ),
        ]);
    }

    /**
     * Save the terms the client is proposing.
     */
    public function update(SaveAgreementRequest $request, Team $currentTeam, Agreement $agreement): RedirectResponse
    {
        DB::transaction(function () use ($request, $agreement): void {
            $agreement->update($request->safe()->only([
                'scope_summary', 'deliverables', 'intellectual_property_terms',
                'confidentiality_terms', 'academic_terms', 'starts_on', 'ends_on',
            ]));

            $this->scheduleMilestones($agreement, $request->array('milestones'));

            $agreement->refresh()->syncTotalAmount();
        });

        return back()->with('success', 'Terms saved.');
    }

    /**
     * Write the timeline the client set: each phase's dates.
     *
     * The phases themselves are not this form's to change: they are fixed
     * (Objective & Scope, Turnover), and Section VII's services fill the first
     * as tasks once the work starts (SeedServiceTasks). So only
     * rows already on this agreement are touched, and only their dates and the
     * dormant amount column; an id from another agreement matches nothing.
     *
     * Under the SDPC memorandum the timeline is Turnover's dates only
     * (owner, 2026-10-07): every other phase is kept undated.
     *
     * @param  array<int, array<string, mixed>>  $milestones
     */
    protected function scheduleMilestones(Agreement $agreement, array $milestones): void
    {
        $onlyTurnoverIsDated = $agreement->template === AgreementTemplate::SdpcMemorandum;
        $turnoverId = $agreement->turnoverPhase()?->id;

        foreach ($milestones as $milestone) {
            $isDated = ! $onlyTurnoverIsDated || (int) $milestone['id'] === $turnoverId;

            $agreement->milestones()->whereKey((int) $milestone['id'])->update([
                'amount' => (int) $milestone['amount'],
                'starts_on' => $isDated ? ($milestone['starts_on'] ?? null) : null,
                'ends_on' => $isDated ? ($milestone['ends_on'] ?? null) : null,
            ]);
        }
    }

    /**
     * Get every agreement the given user is a party to.
     *
     * @return Builder<Agreement>
     */
    protected function visibleTo(User $user)
    {
        /*
         * The leaders of the teams this user is on. Their signed contracts are
         * the ones the user is building against as a teammate — see
         * AgreementPolicy::view, which decides the same thing for one row.
         */
        $teamLeaders = Membership::query()
            ->select('user_id')
            ->where('role', TeamRole::Owner->value)
            ->whereIn('team_id', $user->teams()->select('teams.id'));

        return Agreement::query()
            ->where(fn ($query) => $query
                ->where('student_id', $user->id)
                ->orWhereIn('team_id', $user->teams()->select('teams.id'))
                ->orWhere(fn ($teammate) => $teammate
                    ->whereIn('student_id', $teamLeaders)
                    ->whereIn('status', [AgreementStatus::Active, AgreementStatus::Completed])));
    }

    /**
     * Get the side of the agreement the given user sits on.
     */
    protected function partyFor(User $user, Agreement $agreement): ?AgreementParty
    {
        if ($user->id === $agreement->student_id) {
            return AgreementParty::Student;
        }

        return $user->belongsToTeam($agreement->team) ? AgreementParty::Client : null;
    }
}
