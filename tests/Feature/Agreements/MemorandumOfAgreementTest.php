<?php

namespace Tests\Feature\Agreements;

use App\Actions\Agreements\DraftAgreement;
use App\Actions\Agreements\SupersedeAgreement;
use App\Enums\AgreementParty;
use App\Enums\AgreementStatus;
use App\Enums\AgreementTemplate;
use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Enums\TeamRole;
use App\Models\Agreement;
use App\Models\Application;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The contract reads as the school's Memorandum of Agreement.
 *
 * Every new agreement uses it, with the blanks of the paper form filled from
 * the agreement. One that somebody signed before it arrived keeps the clauses
 * that signature was given for.
 *
 * Routes are pinned with an explicit current_team; see .ai/rules/feature.md.
 */
class MemorandumOfAgreementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_agreement_is_a_memorandum_with_its_blanks_filled(): void
    {
        [$owner, $student, $agreement] = $this->agreement();

        $agreement->update(['starts_on' => '2026-10-01']);

        $this->assertSame(AgreementTemplate::Memorandum, $agreement->fresh()->template);

        foreach ([$owner, $student] as $reader) {
            $this->actingAs($reader)
                ->get(route('agreements.contract', [
                    'current_team' => $reader->currentTeam,
                    'agreement' => $agreement,
                ]))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('agreement.template', 'memorandum')
                    ->where('agreement.memorandum.title', 'Memorandum of Agreement')
                    ->where('agreement.memorandum.parties.client', 'Northwind Trading')
                    ->where('agreement.memorandum.parties.developer', $student->name)
                    ->where('agreement.memorandum.purpose', 'This Agreement establishes cooperation between the Client and the Student Developer Team for the project entitled "Inventory Portal", setting clear responsibilities, scope, and terms.')
                    ->has('agreement.memorandum.commitments', 3)
                    ->where('agreement.memorandum.sections.0.heading', 'Client')
                    ->where('agreement.memorandum.sections.0.items.2', 'Discuss and agree on payment terms directly with the Student Developer Team.')
                    ->where('agreement.memorandum.sections.1.heading', 'Student Developer Team')
                    ->where('agreement.memorandum.sections.3.heading', 'Duration')
                    ->where('agreement.memorandum.sections.3.body', 'This Agreement shall take effect on October 1, 2026 and remain valid **until the Student/Team has successfully passed Capstone 2**, unless terminated earlier by mutual consent.')
                    ->where('agreement.memorandum.sections.5.heading', 'Confidentiality')
                    ->where('agreement.memorandum.signedOn', null)
                    ->where('agreement.acknowledgements', fn ($acknowledgements): bool => collect($acknowledgements)->pluck('key')->all()
                        === ['moa_responsibilities', 'moa_scope', 'moa_confidentiality', 'moa_duration']));
        }
    }

    public function test_the_duration_says_so_when_no_start_date_is_set(): void
    {
        [$owner, , $agreement] = $this->agreement();

        $this->actingAs($owner)
            ->get(route('agreements.contract', ['current_team' => $owner->currentTeam, 'agreement' => $agreement]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement.memorandum.sections.3.body', fn (string $body): bool => str_starts_with($body, 'This Agreement shall take effect on the date both parties sign and')));
    }

    /**
     * The student side is named as a team when the student is on one with
     * other people.
     */
    public function test_a_student_on_a_team_is_named_by_the_team(): void
    {
        [$owner, $student, $agreement] = $this->agreement();

        $team = Team::factory()->create(['name' => 'Byte Builders', 'is_personal' => false]);
        $team->members()->attach($student, ['role' => TeamRole::Owner->value]);
        $team->members()->attach(User::factory()->student()->create(), ['role' => TeamRole::LeadProgrammer->value]);
        $student->switchTeam($team);

        $this->actingAs($owner)
            ->get(route('agreements.contract', ['current_team' => $owner->currentTeam, 'agreement' => $agreement]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement.memorandum.parties.developer', 'Byte Builders'));
    }

    /**
     * The memorandum's own acknowledgements are the ones signing asks for.
     */
    public function test_signing_a_memorandum_takes_its_own_statements(): void
    {
        [$owner, $student, $agreement] = $this->agreement(dated: true);

        $this->actingAs($student)
            ->post($this->signUrl($student, $agreement), [
                'signed_name' => $student->name,
                'acknowledgements' => array_keys(config('agreements.clause_acknowledgements')),
            ])
            ->assertSessionHasErrors('acknowledgements.0');

        $this->assertSame(0, $agreement->signatures()->count());

        $this->actingAs($student)
            ->post($this->signUrl($student, $agreement), [
                'signed_name' => $student->name,
                'acknowledgements' => array_keys(config('agreements.acknowledgements')),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $agreement->signatures()->count());
    }

    /**
     * The memorandum leaves payment to the two sides, and the screens no
     * longer ask for a price — so a milestone at zero does not stop signing.
     * A missing deadline still does.
     */
    public function test_an_agreement_with_no_amounts_can_be_signed_once_it_is_dated(): void
    {
        [$owner, $student, $agreement] = $this->agreement();

        $this->assertSame(0, (int) $agreement->milestones()->sum('amount'));

        $this->actingAs($owner)
            ->post($this->signUrl($owner, $agreement), $this->memorandumSignature($owner->name))
            ->assertSessionHasErrors(['signed_name' => 'Every milestone needs an end date before this agreement can be signed.']);

        $this->date($agreement);

        $this->actingAs($owner)
            ->post($this->signUrl($owner, $agreement), $this->memorandumSignature($owner->name))
            ->assertSessionHasNoErrors();

        $this->actingAs($student)
            ->post($this->signUrl($student, $agreement), $this->memorandumSignature($student->name))
            ->assertSessionHasNoErrors();

        $this->assertSame(AgreementStatus::Active, $agreement->fresh()->status);
    }

    public function test_a_signed_memorandum_says_when_it_was_signed(): void
    {
        [$owner, $student, $agreement] = $this->agreement(dated: true);

        $this->travelTo('2026-09-16 10:00:00');

        foreach ([$owner, $student] as $signer) {
            $this->actingAs($signer)
                ->post($this->signUrl($signer, $agreement), $this->memorandumSignature($signer->name))
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($owner)
            ->get(route('agreements.contract', ['current_team' => $owner->currentTeam, 'agreement' => $agreement]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement.memorandum.signedOn', 'Signed this 16th day of September, 2026, on SDPC.'));
    }

    /**
     * An agreement signed under the earlier clauses keeps them, and keeps
     * asking for the statements that went with them.
     */
    public function test_an_agreement_signed_under_the_earlier_clauses_keeps_them(): void
    {
        [$owner, $student, $agreement] = $this->agreement(dated: true);

        $agreement->update(['template' => AgreementTemplate::Clauses]);

        $this->actingAs($student)
            ->get(route('agreements.contract', ['current_team' => $student->currentTeam, 'agreement' => $agreement]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement.template', 'clauses')
                ->where('agreement.memorandum', null)
                ->where('agreement.terms.intellectualProperty', config('agreements.default_terms.intellectual_property'))
                ->where('agreement.acknowledgements.0.key', 'intellectual_property'));

        $this->actingAs($student)
            ->post($this->signUrl($student, $agreement), [
                'signed_name' => $student->name,
                'acknowledgements' => array_keys(config('agreements.clause_acknowledgements')),
            ])
            ->assertSessionHasNoErrors();
    }

    /**
     * Existing agreements: anything somebody signed stays on the clauses;
     * anything nobody has signed moves to the memorandum.
     */
    public function test_the_migration_moves_only_unsigned_agreements_to_the_memorandum(): void
    {
        [, $student, $signed] = $this->agreement(dated: true);
        [, , $unsigned] = $this->agreement();

        $signed->signatures()->create([
            'user_id' => $student->id,
            'party' => AgreementParty::Student,
            'signed_name' => $student->name,
            'acknowledgements' => array_keys(config('agreements.clause_acknowledgements')),
            'signed_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_16_184127_add_template_to_agreements_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame(AgreementTemplate::Clauses, $signed->fresh()->template);
        $this->assertSame(AgreementTemplate::Memorandum, $unsigned->fresh()->template);
    }

    /**
     * A change request writes a fresh, unsigned version — a memorandum, even
     * when the version it replaces was on the earlier clauses.
     */
    public function test_a_revised_version_is_a_memorandum(): void
    {
        [$owner, , $agreement] = $this->agreement();
        $agreement->update(['template' => AgreementTemplate::Clauses]);

        $successor = app(SupersedeAgreement::class)->handle($agreement->fresh(), $owner, 'Please move the deadline.');

        $this->assertSame(AgreementTemplate::Memorandum, $successor->fresh()->template);
    }

    /**
     * The client's editor no longer offers the earlier clauses, so saving
     * without them must still work.
     */
    public function test_the_client_can_save_terms_without_the_earlier_clauses(): void
    {
        [$owner, , $agreement] = $this->agreement();
        $milestones = $agreement->milestones;

        $this->actingAs($owner)
            ->patch(route('agreements.update', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]), [
                'scope_summary' => 'A customer portal.',
                'deliverables' => ['Login', 'Invoices'],
                'milestones' => $milestones->map(fn ($milestone) => [
                    'id' => $milestone->id,
                    'title' => $milestone->title,
                    'description' => null,
                    'amount' => 0,
                    'starts_on' => null,
                    'ends_on' => '2026-12-01',
                ])->all(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('A customer portal.', $agreement->fresh()->scope_summary);
        $this->assertSame(config('agreements.default_terms.intellectual_property'), $agreement->fresh()->intellectual_property_terms);
    }

    /**
     * Both parties can download the school's blank memorandum as a PDF,
     * named after their agreement.
     */
    public function test_both_parties_can_download_the_memorandum_pdf(): void
    {
        [$owner, $student, $agreement] = $this->agreement();

        foreach ([$owner, $student] as $reader) {
            $response = $this->actingAs($reader)
                ->get(route('agreements.memorandum', [
                    'current_team' => $reader->currentTeam,
                    'agreement' => $agreement,
                ]))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')
                ->assertDownload($agreement->reference.' Memorandum of Agreement.pdf');

            $this->assertSame(
                file_get_contents(resource_path('documents/memorandum-of-agreement.pdf')),
                file_get_contents($response->baseResponse->getFile()->getPathname()),
            );
        }
    }

    public function test_a_stranger_cannot_download_the_memorandum(): void
    {
        [, , $agreement] = $this->agreement();
        $stranger = User::factory()->student()->approved()->create();

        $this->actingAs($stranger)
            ->get(route('agreements.memorandum', [
                'current_team' => $stranger->currentTeam,
                'agreement' => $agreement,
            ]))
            ->assertForbidden();
    }

    /**
     * An agreement signed under the earlier clauses is not the memorandum,
     * so there is no memorandum to download for it.
     */
    public function test_there_is_no_memorandum_for_an_agreement_on_the_earlier_clauses(): void
    {
        [$owner, , $agreement] = $this->agreement();
        $agreement->update(['template' => AgreementTemplate::Clauses]);

        $this->actingAs($owner)
            ->get(route('agreements.memorandum', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]))
            ->assertNotFound();
    }

    /**
     * A drafted agreement between a client called Northwind Trading and a
     * student, on a posting called Inventory Portal.
     *
     * @return array{0: User, 1: User, 2: Agreement}
     */
    private function agreement(bool $dated = false): array
    {
        $owner = User::factory()->verifiedBusiness()->create();
        $owner->currentTeam->clientProfile?->update(['business_name' => 'Northwind Trading']);

        $student = User::factory()->student()->approved()->create();

        $project = Project::factory()->create([
            'team_id' => $owner->current_team_id,
            'title' => 'Inventory Portal',
            'status' => ProjectStatus::Open,
        ]);

        $application = Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Accepted,
        ]);

        $agreement = app(DraftAgreement::class)->handle($application);

        if ($dated) {
            $this->date($agreement);
        }

        return [$owner->fresh(), $student->fresh(), $agreement->fresh()];
    }

    private function date(Agreement $agreement): void
    {
        $agreement->milestones->each(fn ($milestone, $index) => $milestone->update([
            'starts_on' => now()->addWeeks($index * 3)->toDateString(),
            'ends_on' => now()->addWeeks($index * 3 + 2)->toDateString(),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function memorandumSignature(string $name): array
    {
        return [
            'signed_name' => $name,
            'acknowledgements' => array_keys(config('agreements.acknowledgements')),
        ];
    }

    private function signUrl(User $user, Agreement $agreement): string
    {
        return route('agreements.signatures.store', [
            'current_team' => $user->currentTeam,
            'agreement' => $agreement,
        ]);
    }
}
