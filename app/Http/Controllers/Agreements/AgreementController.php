<?php

namespace App\Http\Controllers\Agreements;

use App\Actions\Agreements\PresentAgreement;
use App\Enums\AgreementParty;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agreements\SaveAgreementRequest;
use App\Models\Agreement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

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
                    'counterparty' => $user->id === $agreement->student_id
                        ? ($agreement->project->team->clientProfile?->business_name
                            ?? $agreement->project->team->name)
                        : $agreement->student->name,
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
     * Save the terms the client is proposing.
     */
    public function update(SaveAgreementRequest $request, Team $currentTeam, Agreement $agreement): RedirectResponse
    {
        DB::transaction(function () use ($request, $agreement): void {
            $agreement->update($request->safe()->only([
                'scope_summary', 'deliverables', 'intellectual_property_terms',
                'confidentiality_terms', 'academic_terms', 'starts_on', 'ends_on',
            ]));

            $this->syncMilestones($agreement, $request->array('milestones'));

            $agreement->refresh()->syncTotalAmount();
        });

        return back()->with('success', 'Terms saved.');
    }

    /**
     * Replace the milestone set with the one the client submitted.
     *
     * Rows the client removed are deleted rather than hidden. Editing is only
     * open before anybody has signed, so nothing being dropped here was ever
     * agreed to, and keeping orphaned milestones would leave the schedule
     * showing work that is no longer in the contract.
     *
     * @param  array<int, array<string, mixed>>  $milestones
     */
    protected function syncMilestones(Agreement $agreement, array $milestones): void
    {
        $keptIds = [];

        foreach (array_values($milestones) as $index => $milestone) {
            $attributes = [
                'position' => $index + 1,
                'title' => $milestone['title'],
                'description' => $milestone['description'] ?? null,
                'amount' => (int) $milestone['amount'],
                'starts_on' => $milestone['starts_on'] ?? null,
                'ends_on' => $milestone['ends_on'] ?? null,
            ];

            $existing = isset($milestone['id'])
                ? $agreement->milestones()->find($milestone['id'])
                : null;

            if ($existing !== null) {
                $existing->update($attributes);
                $keptIds[] = $existing->id;

                continue;
            }

            $keptIds[] = $agreement->milestones()->create($attributes)->id;
        }

        $agreement->milestones()->whereNotIn('id', $keptIds)->delete();
    }

    /**
     * Get every agreement the given user is a party to.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Agreement>
     */
    protected function visibleTo(User $user)
    {
        return Agreement::query()
            ->where(fn ($query) => $query
                ->where('student_id', $user->id)
                ->orWhereIn('team_id', $user->teams()->select('teams.id')));
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
