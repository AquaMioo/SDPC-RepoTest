<?php

namespace App\Actions\Agreements;

use App\Enums\AgreementParty;
use App\Enums\AgreementTemplate;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\AgreementSignature;
use App\Models\User;

/**
 * Shapes an agreement for the screens that read it.
 *
 * The Agreement screen and the Contract screen show the same document from two
 * distances, and both are rendered for either party, so the payload is built
 * once here rather than assembled twice with a chance of drifting apart.
 *
 * Nothing role-specific is hidden from the payload — both sides are entitled to
 * every line of a contract they are being asked to sign. What differs is the
 * `viewer` block, which says what this particular person may do next.
 */
class PresentAgreement
{
    /**
     * Build the payload for one agreement as seen by one person.
     *
     * @return array<string, mixed>
     */
    public function handle(Agreement $agreement, User $viewer, ?AgreementParty $party): array
    {
        $agreement->loadMissing([
            'milestones',
            'signatures.signatory',
            'project.team.clientProfile',
            'student.studentProfile',
            'student.currentTeam',
        ]);

        return [
            'id' => $agreement->id,
            'reference' => $agreement->reference,
            'version' => $agreement->version,
            /* Which wording the contract screen shows — see AgreementTemplate. */
            'template' => $agreement->template->value,
            'status' => $agreement->status->value,
            'statusLabel' => $agreement->status->label(),
            'statusVariant' => $agreement->status->tagVariant(),

            'project' => [
                'slug' => $agreement->project->slug,
                'title' => $agreement->project->title,
                'category' => $agreement->project->category,
                'repositoryUrl' => $agreement->project->repository_url,
            ],

            'client' => [
                'name' => $agreement->project->team->clientProfile?->business_name
                    ?? $agreement->project->team->name,
                'signatoryName' => $this->signatureFor($agreement, AgreementParty::Client)?->signed_name,
            ],
            'student' => [
                'id' => $agreement->student->id,
                'name' => $agreement->student->name,
                'signatoryName' => $this->signatureFor($agreement, AgreementParty::Student)?->signed_name,
            ],

            'scopeSummary' => $agreement->scope_summary,
            'deliverables' => $agreement->deliverables ?? [],
            'terms' => [
                'intellectualProperty' => $agreement->intellectual_property_terms,
                'confidentiality' => $agreement->confidentiality_terms,
                'academic' => $agreement->academic_terms,
            ],
            'memorandum' => $agreement->template === AgreementTemplate::Memorandum
                ? $this->memorandum($agreement)
                : null,

            'startsOn' => $agreement->starts_on?->toDateString(),
            'endsOn' => $agreement->ends_on?->toDateString(),
            'totalAmount' => $agreement->total_amount,
            'progress' => $agreement->progress(),

            'milestones' => $agreement->milestones
                ->map(fn (AgreementMilestone $milestone): array => [
                    'id' => $milestone->id,
                    'position' => $milestone->position,
                    'title' => $milestone->title,
                    'description' => $milestone->description,
                    'amount' => $milestone->amount,
                    'startsOn' => $milestone->starts_on?->toDateString(),
                    'endsOn' => $milestone->ends_on?->toDateString(),
                    'status' => $milestone->status->value,
                    'statusLabel' => $milestone->status->label(),
                    'statusVariant' => $milestone->status->tagVariant(),
                    'reviewNote' => $milestone->review_note,
                ])
                ->values()
                ->all(),

            'signatures' => $agreement->signatures
                ->map(fn (AgreementSignature $signature): array => [
                    'party' => $signature->party->value,
                    'partyLabel' => $signature->party->label(),
                    'signedName' => $signature->signed_name,
                    /* The account id the screen promises to record. */
                    'accountId' => $signature->user_id,
                    'signedAt' => $signature->signed_at->toDayDateTimeString(),
                ])
                ->values()
                ->all(),

            'acknowledgements' => collect($agreement->template->acknowledgements())
                ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),

            'viewer' => [
                'party' => $party?->value,
                'partyLabel' => $party?->label(),
                'hasSigned' => $party !== null && $agreement->isSignedBy($party),
                'canEdit' => $viewer->can('update', $agreement),
                'canSign' => $viewer->can('sign', $agreement),
                'canRequestChanges' => $viewer->can('requestChanges', $agreement),
            ],
        ];
    }

    /**
     * The Memorandum of Agreement with its blanks filled in.
     *
     * @return array{title: string, parties: array{client: string, developer: string}, purpose: string, commitments: list<string>, sections: list<array{heading: string, body: string|null, items: list<string>}>, closing: string, signedOn: string|null}
     */
    protected function memorandum(Agreement $agreement): array
    {
        /** @var array<string, mixed> $template */
        $template = config('agreements.memorandum');

        $fill = fn (string $text): string => strtr($text, [
            ':project' => $agreement->project->title,
            ':starts' => $agreement->starts_on?->format('F j, Y') ?? __('the date both parties sign'),
        ]);

        return [
            'title' => (string) $template['title'],
            'parties' => [
                'client' => $agreement->project->team->clientProfile?->business_name
                    ?? $agreement->project->team->name,
                'developer' => $this->developerName($agreement),
            ],
            'purpose' => $fill((string) $template['purpose']),
            'commitments' => array_values(array_map($fill, (array) $template['commitments'])),
            'sections' => array_values(array_map(fn (array $section): array => [
                'heading' => (string) $section['heading'],
                'body' => isset($section['body']) ? $fill((string) $section['body']) : null,
                'items' => array_values(array_map($fill, (array) ($section['items'] ?? []))),
            ], (array) $template['sections'])),
            'closing' => $fill((string) $template['closing']),
            'signedOn' => $this->signedOn($agreement),
        ];
    }

    /**
     * The student side as the memorandum names it: their team when they are
     * on one with other people, otherwise the student themselves.
     */
    protected function developerName(Agreement $agreement): string
    {
        $student = $agreement->student;
        $team = $student->currentTeam;

        return $team !== null && ! $team->isSolo()
            ? $team->name
            : $student->name;
    }

    /**
     * "Signed this 16th day of September, 2026" once both parties have signed,
     * dated by the second signature.
     */
    protected function signedOn(Agreement $agreement): ?string
    {
        if (! $agreement->isFullySigned()) {
            return null;
        }

        $last = $agreement->signatures->max('signed_at');

        return __('Signed this :day day of :month, :year, on SDPC.', [
            'day' => $last->format('jS'),
            'month' => $last->format('F'),
            'year' => $last->format('Y'),
        ]);
    }

    /**
     * Get one side's signature, if it has been given.
     */
    protected function signatureFor(Agreement $agreement, AgreementParty $party): ?AgreementSignature
    {
        return $agreement->signatures
            ->first(fn (AgreementSignature $signature): bool => $signature->party === $party);
    }
}
