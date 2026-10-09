<?php

namespace App\Actions\Agreements;

use App\Contracts\PaymentGateway;
use App\Enums\AddendumPaymentStatus;
use App\Enums\AddendumStatus;
use App\Enums\AgreementParty;
use App\Enums\TaskStatus;
use App\Models\Addendum;
use App\Models\AddendumPayment;
use App\Models\AddendumService;
use App\Models\AgreementTask;
use App\Models\User;
use App\Policies\AddendumPolicy;
use App\Support\GcashNumber;

/**
 * Shapes one addendum for its screen, its printable copy and its transaction
 * record — one payload, so the three never disagree.
 *
 * GCash numbers only ever leave here masked (09******297). A signed side shows
 * the account it signed with; an unsigned side shows what its representative
 * has registered now.
 */
class PresentAddendum
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private readonly SignAddendum $signAddendum,
        private readonly AddendumPolicy $policy,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * Present the addendum to the given viewer.
     *
     * @return array<string, mixed>
     */
    public function handle(Addendum $addendum, User $viewer): array
    {
        $addendum->loadMissing(['agreement.project.team', 'agreement.student', 'services.author', 'payments.payer', 'tasks']);

        $agreement = $addendum->agreement;
        $template = (array) config('agreements.sdpc_addendum');
        $party = $this->policy->partyFor($viewer, $agreement);

        $client = $addendum->clientRepresentative();
        $student = $addendum->studentRepresentative();
        $clientName = $client?->name ?? $agreement->project->team->name;

        $canEdit = $this->policy->edit($viewer, $addendum);
        $amounts = $addendum->total_amount === null ? null : $addendum->milestoneAmounts();

        return [
            'id' => $addendum->id,
            'reference' => $addendum->reference,
            'status' => $addendum->status->value,
            'statusLabel' => $addendum->status->label(),
            'statusVariant' => $addendum->status->tagVariant(),
            'agreementId' => $agreement->id,
            'agreementReference' => $agreement->reference,
            'projectTitle' => $agreement->project->title,
            'requestedOn' => $addendum->created_at?->format('F j, Y'),
            'executedOn' => $addendum->executed_at?->format('F j, Y'),
            'completedOn' => $addendum->completed_at?->format('F j, Y'),
            'totalAmount' => $addendum->total_amount,
            'limits' => [
                'min' => Addendum::MIN_AMOUNT,
                'max' => Addendum::MAX_AMOUNT,
                'downPaymentPercent' => Addendum::DOWN_PAYMENT_PERCENT,
                'threshold' => Addendum::EXTENSION_THRESHOLD,
            ],
            'document' => [
                'title' => (string) $template['title'],
                'footer' => (string) $template['footer'],
                'cover' => (array) $template['cover'],
                'ca1' => $student->name,
                'ca2' => $clientName,
                'services' => $agreement->project->title,
                'sections' => $this->sections((array) $template['sections'], $amounts),
                'example' => array_values((array) collect($template['sections'])->firstWhere('key', 'services')['example']),
                'entries' => $addendum->services
                    ->map(fn (AddendumService $service): array => $this->entry($addendum, $viewer, $service))
                    ->values()
                    ->all(),
                'gcash' => [
                    'student' => GcashNumber::mask($addendum->student_signed_at !== null ? $addendum->student_gcash_number : $student->gcash_number),
                    'client' => GcashNumber::mask($addendum->client_signed_at !== null ? $addendum->client_gcash_number : $client?->gcash_number),
                ],
                'signatories' => [
                    'student' => [
                        'party' => (string) $template['signatories']['student']['party'],
                        'title' => (string) $template['signatories']['student']['title'],
                        'printName' => $addendum->student_signed_name ?? $student->name,
                        'signedOn' => $addendum->student_signed_at?->format('F j, Y'),
                    ],
                    'client' => [
                        'party' => (string) $template['signatories']['client']['party'],
                        'title' => (string) $template['signatories']['client']['title'],
                        'printName' => $addendum->client_signed_name ?? $clientName,
                        'signedOn' => $addendum->client_signed_at?->format('F j, Y'),
                    ],
                ],
            ],
            'signatures' => array_values(array_filter([
                $addendum->student_signed_at === null ? null : [
                    'party' => 'student',
                    'partyLabel' => 'CA1 · Student Team Lead',
                    'signedName' => $addendum->student_signed_name,
                    'signedAt' => $addendum->student_signed_at->format('j M Y, g:i a'),
                ],
                $addendum->client_signed_at === null ? null : [
                    'party' => 'client',
                    'partyLabel' => 'CA2 · Client Representative',
                    'signedName' => $addendum->client_signed_name,
                    'signedAt' => $addendum->client_signed_at->format('j M Y, g:i a'),
                ],
            ])),
            'payments' => $addendum->payments
                ->map(fn (AddendumPayment $payment): array => [
                    'id' => $payment->id,
                    'milestone' => $payment->milestone,
                    'label' => $payment->label(),
                    'percentage' => $payment->percentage,
                    'amount' => $payment->amount,
                    'amountLabel' => self::peso($payment->amount),
                    'status' => $payment->status->value,
                    'statusLabel' => $payment->status->label(),
                    'invoiceNumber' => $payment->invoice_number,
                    'providerPaymentId' => $payment->provider_payment_id,
                    'gateway' => $payment->gateway,
                    'method' => $payment->payment_method === null ? null : 'GCash',
                    'paidBy' => $payment->status === AddendumPaymentStatus::Paid ? $payment->payer?->name : null,
                    'paidAt' => $payment->paid_at?->format('j M Y, g:i a'),
                    'isPayable' => $addendum->isPayable($payment->milestone),
                ])
                ->values()
                ->all(),
            /* Milestone 2 waits for the work: how much of it the student has handed in. */
            'work' => [
                'taskCount' => $addendum->tasks->count(),
                'handedInCount' => $addendum->tasks->filter(fn (AgreementTask $task): bool => $task->status !== TaskStatus::Open)->count(),
                'isHandedIn' => $addendum->isWorkHandedIn(),
            ],
            'gateway' => $this->gateway->name(),
            'viewer' => [
                'party' => $party?->value,
                'canEdit' => $canEdit,
                'canSign' => $this->policy->sign($viewer, $addendum),
                'hasSigned' => $party !== null && $addendum->isSignedBy($party),
                'canCancel' => $this->policy->cancel($viewer, $addendum),
                'canPay' => $this->policy->pay($viewer, $addendum),
                'signingBlockedBy' => $addendum->status->acceptsSignatures()
                    ? $this->signAddendum->reasonItCannotBeSigned($addendum)
                    : null,
                /* Whether this viewer's own side still has to register a GCash account. */
                'needsGcash' => match ($party) {
                    AgreementParty::Student => ! $student->hasGcashAccount() && $addendum->student_signed_at === null,
                    AgreementParty::Client => ! ($client?->hasGcashAccount() ?? false) && $addendum->client_signed_at === null,
                    default => false,
                },
                /* The client's GCash is the representative's: say whose, when the viewer is someone else on the business. */
                'clientRepresentativeName' => $clientName,
                'isClientRepresentative' => $client !== null && $client->is($viewer),
            ],
            'isOpen' => $addendum->status->isOpen(),
            'isCancelled' => $addendum->status === AddendumStatus::Cancelled,
        ];
    }

    /**
     * Format centavos as pesos: ₱3,000.00.
     */
    public static function peso(int $centavos): string
    {
        return '₱'.number_format($centavos / 100, 2);
    }

    /**
     * The addendum's sections, with Section IV's table filled from the amount.
     *
     * @param  list<array<string, mixed>>  $sections
     * @param  array{1: int, 2: int}|null  $amounts
     * @return list<array<string, mixed>>
     */
    protected function sections(array $sections, ?array $amounts): array
    {
        return array_values(array_map(fn (array $section): array => [
            'numeral' => (string) $section['numeral'],
            'heading' => (string) $section['heading'],
            'key' => $section['key'] ?? null,
            'newPage' => (bool) ($section['new_page'] ?? false),
            'blocks' => array_values(array_map(fn (array $block): array => isset($block['paragraph'])
                ? ['type' => 'paragraph', 'text' => (string) $block['paragraph']]
                : ['type' => 'numbered', 'items' => array_values(array_map('strval', (array) $block['numbered']))],
                (array) $section['blocks'])),
            'milestones' => isset($section['milestones'])
                ? $this->milestoneRows((array) $section['milestones'], $amounts)
                : [],
        ], $sections));
    }

    /**
     * Section IV's rows: each milestone's share and amount, and the total.
     *
     * @param  list<array<string, string>>  $rows
     * @param  array{1: int, 2: int}|null  $amounts
     * @return list<array<string, string|null>>
     */
    protected function milestoneRows(array $rows, ?array $amounts): array
    {
        $down = Addendum::DOWN_PAYMENT_PERCENT;
        $allocations = ["{$down}%", (100 - $down).'%', '100%'];
        $values = $amounts === null ? [null, null, null] : [$amounts[1], $amounts[2], $amounts[1] + $amounts[2]];

        return array_values(array_map(fn (array $row, int $index): array => [
            'name' => $row['name'],
            'description' => $row['description'],
            'allocation' => $allocations[$index],
            'amount' => $values[$index] === null ? null : self::peso($values[$index]),
            'condition' => $row['condition'],
        ], $rows, array_keys($rows)));
    }

    /**
     * One Section II service, as the document prints it and the editor offers it.
     *
     * @return array{id: int, objective: string, scope: string, authorName: string|null, authorSide: string|null, canChange: bool}
     */
    protected function entry(Addendum $addendum, User $viewer, AddendumService $service): array
    {
        $author = $service->author;

        return [
            'id' => $service->id,
            'objective' => $service->objective,
            'scope' => $service->scope,
            'authorName' => $author?->name,
            'authorSide' => $author === null ? null : ($author->id === $addendum->agreement->student_id ? 'student' : 'client'),
            'canChange' => $this->policy->changeService($viewer, $addendum, $service),
        ];
    }
}
