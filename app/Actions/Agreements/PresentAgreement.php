<?php

namespace App\Actions\Agreements;

use App\Enums\AgreementParty;
use App\Enums\AgreementTemplate;
use App\Enums\MemorandumSection;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\AgreementRequirement;
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
     * Create a new action instance.
     */
    public function __construct(private readonly SignAgreement $signAgreement) {}

    /**
     * Build the payload for one agreement as seen by one person.
     *
     * @return array<string, mixed>
     */
    public function handle(Agreement $agreement, User $viewer, ?AgreementParty $party): array
    {
        $agreement->loadMissing([
            'milestones',
            'requirements.author',
            'project.creator',
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
            'moa' => $agreement->template === AgreementTemplate::SdpcMemorandum
                ? $this->sdpcMemorandum($agreement, $viewer)
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
                'canAddRequirements' => $viewer->can('addRequirements', $agreement),
                /* What still stops a signature (an empty Section VII, say), said before anyone tries. */
                'signingBlockedBy' => $agreement->status->acceptsSignatures()
                    ? $this->signAgreement->reasonItCannotBeSigned($agreement)
                    : null,
            ],
        ];
    }

    /**
     * The SDPC Memorandum of Agreement, sections I to X, with its blanks
     * filled in and every party's additions in place.
     *
     * The contract screen and its printable copy both read this one shape, so
     * what either side adds on the screen is exactly what prints. Each
     * addition says who wrote it and whether this reader may change it. An
     * optional section with no additions carries an empty list, and the
     * printable copy then leaves its "add more here" placeholder out.
     *
     * @return array<string, mixed>
     */
    protected function sdpcMemorandum(Agreement $agreement, User $viewer): array
    {
        /** @var array<string, mixed> $template */
        $template = config('agreements.sdpc_memorandum');

        $parties = $this->moaParties($agreement);

        $fill = fn (string $text): string => strtr($text, [
            ':ca1_representative' => $parties['ca1Representative'],
            ':ca2_representative' => $parties['ca2Representative'],
            ':ca1' => $parties['ca1'],
            ':ca2' => $parties['ca2'],
            ':services' => $parties['services'],
        ]);

        $requirements = $agreement->requirements
            ->groupBy(fn (AgreementRequirement $requirement): string => $requirement->section->value);

        $sections = array_map(function (array $section) use ($agreement, $viewer, $fill, $requirements): array {
            $key = $section['section'] ?? null;

            $entries = $key === null ? [] : ($requirements[$key] ?? collect())
                ->map(fn (AgreementRequirement $requirement): array => $this->moaEntry($agreement, $viewer, $requirement))
                ->values()
                ->all();

            return [
                'key' => $key,
                'numeral' => (string) $section['numeral'],
                'heading' => (string) $section['heading'],
                'newPage' => (bool) ($section['new_page'] ?? false),
                'blocks' => array_values(array_map(fn (array $block): array => match (true) {
                    isset($block['paragraph']) => ['type' => 'paragraph', 'text' => $fill((string) $block['paragraph'])],
                    isset($block['lettered']) => ['type' => 'lettered', 'items' => array_values(array_map($fill, (array) $block['lettered']))],
                    /* A numbered item is its text, or its text with a lettered list under it. */
                    default => ['type' => 'numbered', 'items' => array_values(array_map(fn (string|array $item): array => is_string($item)
                        ? ['text' => $fill($item), 'lettered' => []]
                        : ['text' => $fill((string) $item['text']), 'lettered' => array_values(array_map($fill, (array) ($item['lettered'] ?? [])))],
                        (array) ($block['numbered'] ?? [])))],
                }, (array) $section['blocks'])),
                'addition' => isset($section['addition']) ? [
                    'as' => (string) $section['addition']['as'],
                    'placeholder' => (string) $section['addition']['placeholder'],
                    'example' => array_values((array) ($section['addition']['example'] ?? [])),
                    'isRequired' => $key !== null && MemorandumSection::from($key)->isRequired(),
                ] : null,
                'entries' => $entries,
            ];
        }, (array) $template['sections']);

        return [
            'title' => (string) $template['title'],
            'footer' => (string) $template['footer'],
            ...$parties,
            'sections' => array_values($sections),
            'signatories' => [
                'client' => $this->moaSignatory($agreement, AgreementParty::Client, $parties['ca1Representative']),
                'student' => $this->moaSignatory($agreement, AgreementParty::Student, $parties['ca2Representative']),
            ],
        ];
    }

    /**
     * The legend that fills the memorandum's blanks.
     *
     * CA1, the Contracting Agency, is the Client Representative: the name on
     * the client account that posted the project (the business's owner
     * account when the poster is gone). CA2, the Customer Agency, is the
     * Student Representative: the name on the student account. The
     * Description of Services is the capstone/project title. The same two
     * names are the individuals Section III and the signature lines name.
     *
     * @return array{ca1: string, ca2: string, ca1Representative: string, ca2Representative: string, services: string}
     */
    protected function moaParties(Agreement $agreement): array
    {
        $team = $agreement->project->team;

        $client = $agreement->project->creator?->name
            ?? $team->owner()?->name
            ?? $team->name;

        return [
            'ca1' => $client,
            'ca2' => $agreement->student->name,
            'ca1Representative' => $client,
            'ca2Representative' => $agreement->student->name,
            'services' => $agreement->project->title,
        ];
    }

    /**
     * One party's addition, as the memorandum prints it and the editor offers it.
     *
     * @return array{id: int, title: string|null, body: string, authorName: string|null, authorSide: string|null, canChange: bool}
     */
    protected function moaEntry(Agreement $agreement, User $viewer, AgreementRequirement $entry): array
    {
        $author = $entry->author;

        return [
            'id' => $entry->id,
            /* Section VII's Objective; null in every other section. */
            'title' => $entry->title,
            'body' => $entry->body,
            'authorName' => $author?->name,
            'authorSide' => $author === null ? null : ($author->id === $agreement->student_id ? 'student' : 'client'),
            'canChange' => $viewer->can('changeRequirement', [$agreement, $entry]),
        ];
    }

    /**
     * One side's lines in Section X: the name to print, their title, and the
     * date they signed on SDPC. The signature line itself stays blank for ink.
     *
     * @return array{printName: string, title: string, signedOn: string|null}
     */
    protected function moaSignatory(Agreement $agreement, AgreementParty $party, string $representative): array
    {
        $signature = $this->signatureFor($agreement, $party);

        return [
            'printName' => $signature?->signed_name ?? $representative,
            'title' => (string) config("agreements.sdpc_memorandum.signature_titles.{$party->value}"),
            'signedOn' => $signature?->signed_at->format('F j, Y'),
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
