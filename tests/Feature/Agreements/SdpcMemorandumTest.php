<?php

namespace Tests\Feature\Agreements;

use App\Actions\Agreements\DraftAgreement;
use App\Actions\Agreements\SummariseProgress;
use App\Actions\Agreements\SupersedeAgreement;
use App\Enums\AgreementParty;
use App\Enums\AgreementStatus;
use App\Enums\AgreementTemplate;
use App\Enums\ApplicationStatus;
use App\Enums\MemorandumSection;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\AgreementRequirement;
use App\Models\AgreementTask;
use App\Models\Application;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The SDPC Memorandum of Agreement.
 *
 * Sections I to X in the template's wording, the blanks filled from the
 * agreement (CA1 the client, CA2 the student representative, the Description
 * of Services the project title). Either party adds to the optional sections
 * and to Section VII, which is required and whose services are the project's
 * phases. Only the author changes an addition, and everything locks at the
 * first signature.
 *
 * Routes are pinned with an explicit current_team; see .ai/rules/feature.md.
 */
class SdpcMemorandumTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_agreement_is_the_sdpc_memorandum_with_its_blanks_filled(): void
    {
        [$owner, $student, $agreement] = $this->agreement();

        $this->assertSame(AgreementTemplate::SdpcMemorandum, $agreement->template);
        /* Design and Build are gone: Turnover alone until Section VII adds services. */
        $this->assertSame(['Turnover'], $agreement->milestones->pluck('title')->all());

        foreach ([$owner, $student] as $reader) {
            $this->actingAs($reader)
                ->get($this->url('agreements.contract', $reader, $agreement))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('agreements/contract')
                    ->where('agreement.template', 'sdpc_moa')
                    ->where('agreement.memorandum', null)
                    ->where('agreement.moa.title', 'MEMORANDUM OF AGREEMENT (MOA)')
                    ->where('agreement.moa.ca1', 'Northwind Trading')
                    ->where('agreement.moa.ca2', $student->name)
                    ->where('agreement.moa.ca1Representative', 'Maria Santos')
                    ->where('agreement.moa.ca2Representative', $student->name)
                    ->where('agreement.moa.services', 'Inventory Portal')
                    ->has('agreement.moa.sections', 10)
                    ->where('agreement.moa.sections.0.heading', 'PARTIES')
                    ->where('agreement.moa.sections.0.blocks.0.text', 'The parties to this MOA are the CONTRACTING AGENCY, **Northwind Trading**, hereafter referenced as "CA1" and the CUSTOMER AGENCY, **'.$student->name.'**, referenced as "CA2".')
                    ->where('agreement.moa.sections.2.blocks.0.items.1', 'CA2 designates **'.$student->name.'** as having the final authority on all policy or procedural matters pertaining to CA2.')
                    ->where('agreement.moa.sections.2.blocks.0.items.3', '**Maria Santos** at CA1 and **'.$student->name.'** at CA2 will be the Contract Administrators for this MOA.')
                    ->where('agreement.moa.sections.4.blocks.1.items.0', 'Offer training and meetings as necessary to meet the needs of CA2 in order to enhance effectiveness of **Inventory Portal** provided per this MOA.')
                    ->where('agreement.moa.sections.6.key', 'services')
                    ->where('agreement.moa.sections.6.addition.isRequired', true)
                    ->where('agreement.moa.sections.3.addition.isRequired', false)
                    ->where('agreement.moa.sections.9.heading', 'EFFECTIVE DATE AND SIGNATURE')
                    ->where('agreement.moa.signatories.client.printName', 'Maria Santos')
                    ->where('agreement.moa.signatories.client.title', 'Client Representative')
                    ->where('agreement.moa.signatories.student.title', 'Student Representative')
                    ->where('agreement.moa.signatories.student.signedOn', null)
                    ->where('agreement.viewer.canAddRequirements', true));
        }
    }

    /**
     * No blank of the template is left for the parties to fill by hand.
     */
    public function test_no_template_blank_survives_in_the_filled_memorandum(): void
    {
        [$owner, , $agreement] = $this->agreement();

        $this->actingAs($owner)
            ->get($this->url('agreements.contract', $owner, $agreement))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement.moa.sections', function ($sections): bool {
                    $text = json_encode($sections);

                    foreach ([':ca1', ':ca2', ':services', 'INDIVIDUAL OR POSITION', 'CONTRACTING AGENCY NAME', 'CUSTOMER AGENCY NAME', '(Capstone/Project Title)'] as $blank) {
                        if (str_contains($text, $blank)) {
                            return false;
                        }
                    }

                    return true;
                }));
    }

    public function test_ca1_is_the_representative_when_the_client_has_no_company_name(): void
    {
        [$owner, , $agreement] = $this->agreement();
        $owner->currentTeam->clientProfile->update(['business_name' => '']);

        $this->actingAs($owner)
            ->get($this->url('agreements.contract', $owner, $agreement))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement.moa.ca1', 'Maria Santos'));
    }

    public function test_both_parties_add_requirements_and_only_the_author_may_change_one(): void
    {
        [$owner, $student, $agreement] = $this->agreement();

        $this->addRequirement($owner, $agreement, MemorandumSection::CustomerAgency, 'Attend the weekly stand-up.');
        $this->addRequirement($student, $agreement, MemorandumSection::Terms, 'Source code is handed over on a USB drive.');

        $mine = AgreementRequirement::query()->where('user_id', $owner->id)->firstOrFail();
        $theirs = AgreementRequirement::query()->where('user_id', $student->id)->firstOrFail();

        $this->actingAs($owner)
            ->get($this->url('agreements.contract', $owner, $agreement))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement.moa.sections.3.entries.0.body', 'Attend the weekly stand-up.')
                ->where('agreement.moa.sections.3.entries.0.authorSide', 'client')
                ->where('agreement.moa.sections.3.entries.0.canChange', true)
                ->where('agreement.moa.sections.8.entries.0.body', 'Source code is handed over on a USB drive.')
                ->where('agreement.moa.sections.8.entries.0.authorSide', 'student')
                ->where('agreement.moa.sections.8.entries.0.canChange', false)
                /* An optional section nobody added to carries nothing to print. */
                ->where('agreement.moa.sections.4.entries', [])
                ->where('agreement.moa.sections.7.entries', []));

        /* The other party's line: neither edited nor removed. */
        $this->actingAs($owner)
            ->patch($this->url('agreements.requirements.update', $owner, $agreement, ['requirement' => $theirs]), ['body' => 'Changed by the client'])
            ->assertForbidden();
        $this->actingAs($owner)
            ->delete($this->url('agreements.requirements.destroy', $owner, $agreement, ['requirement' => $theirs]))
            ->assertForbidden();

        /* Their own line: both. */
        $this->actingAs($owner)
            ->patch($this->url('agreements.requirements.update', $owner, $agreement, ['requirement' => $mine]), ['body' => 'Attend the stand-up every Monday.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Attend the stand-up every Monday.', $mine->fresh()->body);

        $this->actingAs($student)
            ->delete($this->url('agreements.requirements.destroy', $student, $agreement, ['requirement' => $theirs]))
            ->assertRedirect();

        $this->assertNull($theirs->fresh());
        $this->assertSame(1, AgreementRequirement::query()->count());
    }

    public function test_a_requirement_needs_text_and_an_open_section(): void
    {
        [$owner, , $agreement] = $this->agreement();

        $this->actingAs($owner)
            ->post($this->url('agreements.requirements.store', $owner, $agreement), ['section' => 'customer_agency', 'body' => ''])
            ->assertSessionHasErrors(['body' => 'Write the requirement before saving it.']);

        /* Section VII is services, through its own modal; the fixed sections take nothing. */
        foreach (['services', 'parties', 'purpose'] as $section) {
            $this->actingAs($owner)
                ->post($this->url('agreements.requirements.store', $owner, $agreement), ['section' => $section, 'body' => 'Anything'])
                ->assertSessionHasErrors('section');
        }

        $this->assertSame(0, AgreementRequirement::query()->count());
    }

    public function test_a_stranger_cannot_add_to_the_memorandum(): void
    {
        [, , $agreement] = $this->agreement();
        $stranger = User::factory()->student()->approved()->create();

        $this->actingAs($stranger)
            ->post($this->url('agreements.requirements.store', $stranger, $agreement), ['section' => 'joint', 'body' => 'Sneaked in'])
            ->assertForbidden();
        $this->actingAs($stranger)
            ->post($this->url('agreements.services.store', $stranger, $agreement), ['objective' => 'x', 'scope' => 'y'])
            ->assertForbidden();
    }

    /**
     * Each Section VII service is a phase, written before Turnover, so
     * Turnover stays the last phase whatever is added or removed.
     */
    public function test_a_service_becomes_a_phase_before_turnover(): void
    {
        [$owner, $student, $agreement] = $this->agreement();

        $this->addService($owner, $agreement, 'Inventory module', 'Stock, suppliers and reorder alerts.');
        $this->addService($student, $agreement, 'Reports module', 'Monthly sales and stock reports.');

        $phases = $agreement->refresh()->milestones;

        $this->assertSame(['Inventory module', 'Reports module', 'Turnover'], $phases->pluck('title')->all());
        $this->assertSame([1, 2, 3], $phases->pluck('position')->all());
        $this->assertSame('Stock, suppliers and reorder alerts.', $phases[0]->description);
        $this->assertSame($owner->id, $phases[0]->added_by);
        $this->assertSame($student->id, $phases[1]->added_by);
        $this->assertNull($phases[2]->added_by);

        /* Removing the first closes the gap; Turnover is still last. */
        $this->actingAs($owner)
            ->delete($this->url('agreements.services.destroy', $owner, $agreement, ['service' => $phases[0]]))
            ->assertRedirect();

        $after = $agreement->refresh()->milestones;
        $this->assertSame(['Reports module', 'Turnover'], $after->pluck('title')->all());
        $this->assertSame([1, 2], $after->pluck('position')->all());
    }

    public function test_only_the_author_changes_a_service_and_turnover_is_never_one(): void
    {
        [$owner, $student, $agreement] = $this->agreement();

        $this->addService($owner, $agreement, 'Inventory module', 'Stock and suppliers.');
        [$service, $turnover] = $agreement->refresh()->milestones;

        $this->actingAs($student)
            ->patch($this->url('agreements.services.update', $student, $agreement, ['service' => $service]), ['objective' => 'Hijacked', 'scope' => 'x'])
            ->assertForbidden();
        $this->actingAs($student)
            ->delete($this->url('agreements.services.destroy', $student, $agreement, ['service' => $service]))
            ->assertForbidden();

        foreach ([$owner, $student] as $party) {
            $this->actingAs($party)
                ->patch($this->url('agreements.services.update', $party, $agreement, ['service' => $turnover]), ['objective' => 'Renamed', 'scope' => 'x'])
                ->assertForbidden();
            $this->actingAs($party)
                ->delete($this->url('agreements.services.destroy', $party, $agreement, ['service' => $turnover]))
                ->assertForbidden();
        }

        $this->actingAs($owner)
            ->patch($this->url('agreements.services.update', $owner, $agreement, ['service' => $service]), ['objective' => 'Inventory and suppliers', 'scope' => 'Stock, suppliers and purchase orders.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Inventory and suppliers', $service->fresh()->title);
        $this->assertSame('Stock, suppliers and purchase orders.', $service->fresh()->description);
        $this->assertSame('Turnover', $turnover->fresh()->title);
    }

    public function test_a_service_needs_both_an_objective_and_a_scope(): void
    {
        [$owner, , $agreement] = $this->agreement();

        $this->actingAs($owner)
            ->post($this->url('agreements.services.store', $owner, $agreement), ['objective' => '', 'scope' => ''])
            ->assertSessionHasErrors([
                'objective' => 'Give the service its objective (a title).',
                'scope' => 'Describe the scope of the service.',
            ]);

        $this->assertSame(1, $agreement->milestones()->count());
    }

    /**
     * Section VII is required: with no service in it, neither the client nor
     * the student may sign, and both screens say why before anyone tries.
     */
    public function test_neither_party_can_sign_while_section_vii_is_empty(): void
    {
        [$owner, $student, $agreement] = $this->agreement();
        $this->dateTurnover($agreement);

        $message = 'Section VII. Description of Services needs at least one service (an objective and its scope) before this agreement can be signed.';

        foreach ([$owner, $student] as $party) {
            $this->actingAs($party)
                ->get($this->url('agreements.show', $party, $agreement))
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('agreement.viewer.signingBlockedBy', $message));

            $this->actingAs($party)
                ->post($this->url('agreements.signatures.store', $party, $agreement), $this->signature($party->name))
                ->assertSessionHasErrors(['signed_name' => $message]);
        }

        $this->assertSame(0, $agreement->signatures()->count());

        /* One service, dated, and both may sign: the second signature starts the work. */
        $this->addService($student, $agreement, 'Inventory module', 'Stock and suppliers.');
        $this->dateServices($agreement);

        $this->actingAs($owner)
            ->get($this->url('agreements.show', $owner, $agreement))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement.viewer.signingBlockedBy', null));

        foreach ([$owner, $student] as $party) {
            $this->actingAs($party)
                ->post($this->url('agreements.signatures.store', $party, $agreement), $this->signature($party->name))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(AgreementStatus::Active, $agreement->fresh()->status);
    }

    public function test_a_service_without_its_scope_blocks_signing(): void
    {
        [$owner, , $agreement] = $this->agreement();
        $this->dateTurnover($agreement);

        /* A phase from before the SDPC memorandum: a name, no scope, no author. */
        $turnover = $agreement->milestones->last();
        $turnover->update(['position' => 2]);
        AgreementMilestone::factory()->create([
            'agreement_id' => $agreement->id,
            'position' => 1,
            'title' => 'Design',
            'description' => null,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addWeek()->toDateString(),
        ]);

        $this->actingAs($owner)
            ->post($this->url('agreements.signatures.store', $owner, $agreement), $this->signature($owner->name))
            ->assertSessionHasErrors(['signed_name' => 'Every service in Section VII needs its scope before this agreement can be signed.']);
    }

    public function test_a_turnover_shorter_than_a_month_blocks_signing(): void
    {
        [$owner, , $agreement] = $this->agreement();
        $this->addService($owner, $agreement, 'Inventory module', 'Stock and suppliers.');
        $this->dateServices($agreement);

        $agreement->refresh()->milestones->last()->update([
            'starts_on' => now()->addWeeks(3)->toDateString(),
            'ends_on' => now()->addWeeks(5)->toDateString(),
        ]);

        $this->actingAs($owner)
            ->post($this->url('agreements.signatures.store', $owner, $agreement), $this->signature($owner->name))
            ->assertSessionHasErrors(['signed_name' => 'Turnover has to run at least one month. End it on or after '.now()->addWeeks(3)->addMonthNoOverflow()->format('j M Y').'.']);
    }

    /**
     * The first signature locks every addition, for both parties.
     */
    public function test_the_memorandum_locks_the_moment_anybody_signs(): void
    {
        [$owner, $student, $agreement] = $this->agreement();
        $this->addRequirement($owner, $agreement, MemorandumSection::Joint, 'Meet every Friday.');
        $this->addService($owner, $agreement, 'Inventory module', 'Stock and suppliers.');
        $this->dateServices($agreement);

        $requirement = AgreementRequirement::query()->firstOrFail();
        $service = $agreement->refresh()->milestones->first();

        $this->actingAs($student)
            ->post($this->url('agreements.signatures.store', $student, $agreement), $this->signature($student->name))
            ->assertSessionHasNoErrors();

        foreach ([$owner, $student] as $party) {
            $this->actingAs($party)
                ->post($this->url('agreements.requirements.store', $party, $agreement), ['section' => 'joint', 'body' => 'Late addition'])
                ->assertForbidden();
            $this->actingAs($party)
                ->post($this->url('agreements.services.store', $party, $agreement), ['objective' => 'Late', 'scope' => 'Late'])
                ->assertForbidden();
        }

        $this->actingAs($owner)
            ->patch($this->url('agreements.requirements.update', $owner, $agreement, ['requirement' => $requirement]), ['body' => 'Moved after signing'])
            ->assertForbidden();
        $this->actingAs($owner)
            ->delete($this->url('agreements.services.destroy', $owner, $agreement, ['service' => $service]))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get($this->url('agreements.contract', $owner, $agreement))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement.viewer.canAddRequirements', false)
                ->where('agreement.moa.sections.5.entries.0.canChange', false)
                ->where('agreement.moa.signatories.student.printName', $student->name)
                ->where('agreement.moa.signatories.student.signedOn', now()->format('F j, Y')));
    }

    /**
     * The finished copy: the same document the contract screen edits, for
     * both parties, laid out to print.
     */
    public function test_both_parties_get_the_printable_copy(): void
    {
        [$owner, $student, $agreement] = $this->agreement();
        $this->addService($student, $agreement, 'Inventory module', 'Stock and suppliers.');
        $this->addRequirement($owner, $agreement, MemorandumSection::Compliance, 'Follow the company data retention policy.');

        foreach ([$owner, $student] as $reader) {
            $this->actingAs($reader)
                ->get($this->url('agreements.printable', $reader, $agreement))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('agreements/printable')
                    ->where('agreement.moa.sections.6.entries.0.title', 'Inventory module')
                    ->where('agreement.moa.sections.6.entries.0.body', 'Stock and suppliers.')
                    ->where('agreement.moa.sections.7.entries.0.body', 'Follow the company data retention policy.')
                    ->where('agreement.moa.sections.3.entries', []));
        }

        $stranger = User::factory()->student()->approved()->create();

        $this->actingAs($stranger)
            ->get($this->url('agreements.printable', $stranger, $agreement))
            ->assertForbidden();

        $agreement->update(['template' => AgreementTemplate::Clauses]);

        $this->actingAs($owner)
            ->get($this->url('agreements.printable', $owner, $agreement))
            ->assertNotFound();
    }

    /**
     * The template before anything is added: the school's blank SDPC form.
     */
    public function test_the_blank_template_is_the_sdpc_form(): void
    {
        [$owner, , $agreement] = $this->agreement();

        $response = $this->actingAs($owner)
            ->get($this->url('agreements.memorandum', $owner, $agreement))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload($agreement->reference.' Memorandum of Agreement.pdf');

        $this->assertSame(
            file_get_contents(resource_path('documents/sdpc-memorandum-of-agreement.pdf')),
            file_get_contents($response->baseResponse->getFile()->getPathname()),
        );
    }

    public function test_a_revision_keeps_every_addition_and_its_author(): void
    {
        [$owner, $student, $agreement] = $this->agreement();
        $this->addRequirement($student, $agreement, MemorandumSection::ContractingAgency, 'Provide test data.');
        $this->addService($owner, $agreement, 'Inventory module', 'Stock and suppliers.');

        $successor = app(SupersedeAgreement::class)->handle($agreement->fresh(), $student, 'Please add a reports module.');

        $this->assertSame(['Inventory module', 'Turnover'], $successor->milestones->pluck('title')->all());
        $this->assertSame($owner->id, $successor->milestones->first()->added_by);
        $this->assertSame('Provide test data.', $successor->requirements()->firstOrFail()->body);
        $this->assertSame($student->id, $successor->requirements()->firstOrFail()->user_id);
    }

    /**
     * Project Monitoring reads the Objective and Scope straight from the
     * Section VII entries: no Design or Build.
     */
    public function test_project_management_shows_each_service_as_objective_and_scope(): void
    {
        [$owner, $student, $agreement] = $this->signedAgreement();

        $this->actingAs($student)
            ->get(route('project-management', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('agreement.phases', 3)
                ->where('agreement.phases.0.title', 'Inventory module')
                ->where('agreement.phases.0.description', 'Stock and suppliers.')
                ->where('agreement.phases.0.isTurnover', false)
                ->where('agreement.phases.1.title', 'Reports module')
                ->where('agreement.phases.1.description', 'Monthly reports.')
                ->where('agreement.phases.2.title', 'Turnover')
                ->where('agreement.phases.2.isTurnover', true));
    }

    /**
     * Turnover's tasks count for nothing in the percentage, on every screen
     * that reads it.
     */
    public function test_turnover_is_left_out_of_the_progress_figure(): void
    {
        [$owner, $student, $agreement] = $this->signedAgreement();
        [$inventory, $reports, $turnover] = $agreement->milestones;

        AgreementTask::factory()->create(['agreement_milestone_id' => $inventory->id, 'status' => TaskStatus::Verified]);
        AgreementTask::factory()->create(['agreement_milestone_id' => $reports->id, 'status' => TaskStatus::Open]);
        /* Turnover: one verified, two open. None of them may move the figure. */
        AgreementTask::factory()->create(['agreement_milestone_id' => $turnover->id, 'status' => TaskStatus::Verified]);
        AgreementTask::factory()->count(2)->create(['agreement_milestone_id' => $turnover->id, 'status' => TaskStatus::Open]);

        $agreement = $agreement->fresh();

        $this->assertSame(50, $agreement->progress());
        $this->assertSame(2, $agreement->taskCount());
        $this->assertSame(1, $agreement->verifiedTaskCount());

        $summary = app(SummariseProgress::class)->handle($agreement);
        $this->assertSame(50, $summary['progress']);
        $this->assertSame(2, $summary['taskCount']);
        $this->assertTrue($summary['phases'][2]['isTurnover']);
        $this->assertFalse($summary['phases'][0]['isTurnover']);

        $this->actingAs($owner)
            ->get(route('project-management', ['current_team' => $owner->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement.summary.progress', 50)
                ->where('agreement.summary.taskCount', 2)
                ->where('agreement.summary.verifiedCount', 1));
    }

    /**
     * Existing agreements nobody signed move to the SDPC memorandum, without
     * the Design and Build placeholders; signed ones are left as they are.
     */
    public function test_the_migration_moves_only_unsigned_agreements_and_drops_their_placeholders(): void
    {
        [, $student, $signed] = $this->agreement();
        [, , $unsigned] = $this->agreement();

        foreach ([$signed, $unsigned] as $agreement) {
            $agreement->update(['template' => AgreementTemplate::Memorandum]);
            $agreement->milestones()->update(['position' => 3, 'starts_on' => now()->addMonth()->toDateString()]);
            foreach (['Design', 'Build'] as $index => $title) {
                AgreementMilestone::factory()->create(['agreement_id' => $agreement->id, 'position' => $index + 1, 'title' => $title, 'description' => null]);
            }
        }

        $signed->signatures()->create([
            'user_id' => $student->id,
            'party' => AgreementParty::Student,
            'signed_name' => $student->name,
            'acknowledgements' => array_keys(config('agreements.acknowledgements')),
            'signed_at' => now(),
        ]);
        $signed->update(['status' => AgreementStatus::AwaitingSignatures]);

        $migration = require database_path('migrations/2026_10_03_033635_adopt_the_sdpc_memorandum_of_agreement.php');
        $migration->down();
        $migration->up();

        $this->assertSame(AgreementTemplate::SdpcMemorandum, $unsigned->fresh()->template);
        $this->assertSame(['Turnover'], $unsigned->fresh()->milestones->pluck('title')->all());
        $this->assertSame([1], $unsigned->fresh()->milestones->pluck('position')->all());
        /* Turnover keeps the dates it was given. */
        $this->assertSame(now()->addMonth()->toDateString(), $unsigned->fresh()->milestones->first()->starts_on->toDateString());

        $this->assertSame(AgreementTemplate::Memorandum, $signed->fresh()->template);
        $this->assertSame(['Design', 'Build', 'Turnover'], $signed->fresh()->milestones->pluck('title')->all());
        $this->assertTrue(DB::getSchemaBuilder()->hasColumn('agreement_milestones', 'added_by'));
    }

    /**
     * A drafted agreement between Northwind Trading (represented by Maria
     * Santos) and a student, on a posting called Inventory Portal.
     *
     * @return array{0: User, 1: User, 2: Agreement}
     */
    private function agreement(): array
    {
        $owner = User::factory()->verifiedBusiness()->create();
        $owner->currentTeam->clientProfile->update(['business_name' => 'Northwind Trading', 'owner_name' => 'Maria Santos']);

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

        return [$owner->fresh(), $student->fresh(), $agreement->fresh()];
    }

    /**
     * Two services, dated, and both signatures: an active agreement.
     *
     * @return array{0: User, 1: User, 2: Agreement}
     */
    private function signedAgreement(): array
    {
        [$owner, $student, $agreement] = $this->agreement();

        $this->addService($owner, $agreement, 'Inventory module', 'Stock and suppliers.');
        $this->addService($student, $agreement, 'Reports module', 'Monthly reports.');
        $this->dateServices($agreement);

        foreach ([$owner, $student] as $party) {
            $this->actingAs($party)
                ->post($this->url('agreements.signatures.store', $party, $agreement), $this->signature($party->name))
                ->assertSessionHasNoErrors();
        }

        return [$owner, $student, $agreement->fresh()->load('milestones')];
    }

    private function addRequirement(User $party, Agreement $agreement, MemorandumSection $section, string $body): void
    {
        $this->actingAs($party)
            ->post($this->url('agreements.requirements.store', $party, $agreement), ['section' => $section->value, 'body' => $body])
            ->assertSessionHasNoErrors();
    }

    private function addService(User $party, Agreement $agreement, string $objective, string $scope): void
    {
        $this->actingAs($party)
            ->post($this->url('agreements.services.store', $party, $agreement), ['objective' => $objective, 'scope' => $scope])
            ->assertSessionHasNoErrors();
    }

    /**
     * Date every service (they may overlap) and give Turnover a month after them.
     */
    private function dateServices(Agreement $agreement): void
    {
        $agreement->refresh()->servicePhases()->each(fn (AgreementMilestone $service) => $service->update([
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addWeeks(2)->toDateString(),
        ]));

        $this->dateTurnover($agreement);
    }

    private function dateTurnover(Agreement $agreement): void
    {
        $agreement->refresh()->turnoverPhase()->update([
            'starts_on' => now()->addWeeks(3)->toDateString(),
            'ends_on' => now()->addWeeks(3)->addMonth()->toDateString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function signature(string $name): array
    {
        return [
            'signed_name' => $name,
            'acknowledgements' => array_keys(config('agreements.acknowledgements')),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function url(string $name, User $user, Agreement $agreement, array $extra = []): string
    {
        return route($name, [
            'current_team' => $user->currentTeam,
            'agreement' => $agreement,
            ...$extra,
        ]);
    }
}
