<?php

namespace Tests\Feature\Agreements;

use App\Actions\Agreements\DraftAgreement;
use App\Actions\Agreements\SummariseProgress;
use App\Actions\Agreements\SupersedeAgreement;
use App\Enums\AgreementStatus;
use App\Enums\AgreementTemplate;
use App\Enums\ApplicationStatus;
use App\Enums\MemorandumSection;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Agreement;
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
 * Sections I to X in the template's wording, every part numbered, the blanks
 * filled from the agreement: CA1 is the Client Representative (the client
 * account's name), CA2 the Student Representative (the student account's
 * name), the Description of Services the project title. Either party adds to
 * the optional sections and to Section VII, which is required. Section VII
 * never adds a phase: the phases are Objective, Scope and Turnover, and when
 * the work starts each objective becomes a task in Objective and each scope a
 * task in Scope. Only the author changes an addition, and everything locks at
 * the first signature.
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
        /* Design and Build are gone; the phases are Objective, Scope and Turnover. */
        $this->assertSame(['Objective', 'Scope', 'Turnover'], $agreement->milestones->pluck('title')->all());

        foreach ([$owner, $student] as $reader) {
            $this->actingAs($reader)
                ->get($this->url('agreements.contract', $reader, $agreement))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('agreements/contract')
                    ->where('agreement.template', 'sdpc_moa')
                    ->where('agreement.memorandum', null)
                    ->where('agreement.moa.title', 'MEMORANDUM OF AGREEMENT (MOA)')
                    /* CA1 and CA2 are the two accounts' names, never the company. */
                    ->where('agreement.moa.ca1', $owner->name)
                    ->where('agreement.moa.ca2', $student->name)
                    ->where('agreement.moa.ca1Representative', $owner->name)
                    ->where('agreement.moa.ca2Representative', $student->name)
                    ->where('agreement.moa.services', 'Inventory Portal')
                    ->has('agreement.moa.sections', 10)
                    ->where('agreement.moa.sections.0.heading', 'PARTIES')
                    ->where('agreement.moa.sections.0.blocks.0.type', 'numbered')
                    ->where('agreement.moa.sections.0.blocks.0.items.0.text', 'The parties to this MOA are the CONTRACTING AGENCY, **'.$owner->name.'**, hereafter referenced as "CA1" and the CUSTOMER AGENCY, **'.$student->name.'**, referenced as "CA2".')
                    ->where('agreement.moa.sections.2.blocks.0.items.1.text', 'CA2 designates **'.$student->name.'** as having the final authority on all policy or procedural matters pertaining to CA2.')
                    ->where('agreement.moa.sections.2.blocks.0.items.3.text', '**'.$owner->name.'** at CA1 and **'.$student->name.'** at CA2 will be the Contract Administrators for this MOA.')
                    ->where('agreement.moa.sections.4.blocks.1.items.0.text', 'Offer training and meetings as necessary to meet the needs of CA2 in order to enhance effectiveness of **Inventory Portal** provided per this MOA.')
                    ->where('agreement.moa.sections.6.key', 'services')
                    ->where('agreement.moa.sections.6.addition.isRequired', true)
                    ->where('agreement.moa.sections.3.addition.isRequired', false)
                    ->where('agreement.moa.sections.9.heading', 'EFFECTIVE DATE AND SIGNATURE')
                    ->where('agreement.moa.signatories.client.printName', $owner->name)
                    ->where('agreement.moa.signatories.client.title', 'Client Representative')
                    ->where('agreement.moa.signatories.student.title', 'Student Representative')
                    ->where('agreement.moa.signatories.student.signedOn', null)
                    ->where('agreement.viewer.canAddRequirements', true));
        }
    }

    /**
     * Every part of every section is numbered like the template's lists, so
     * no two parts read as one run of text. Only the IV-VI lead-in lines
     * stay unnumbered, as in the template.
     */
    public function test_every_part_of_every_section_is_numbered(): void
    {
        [$owner, , $agreement] = $this->agreement();

        $this->actingAs($owner)
            ->get($this->url('agreements.contract', $owner, $agreement))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement.moa.sections', function ($sections): bool {
                    $sections = collect($sections);
                    $types = $sections->mapWithKeys(fn ($section) => [$section['numeral'] => collect($section['blocks'])->pluck('type')->all()]);

                    $this->assertSame([
                        'I' => ['numbered'],
                        'II' => ['numbered'],
                        'III' => ['numbered'],
                        'IV' => ['paragraph', 'numbered'],
                        'V' => ['paragraph', 'numbered'],
                        'VI' => ['paragraph', 'numbered'],
                        'VII' => [],
                        'VIII' => ['numbered'],
                        'IX' => ['numbered'],
                        'X' => ['numbered'],
                    ], $types->all());

                    $viii = $sections->firstWhere('numeral', 'VIII');
                    $this->assertCount(3, $viii['blocks'][0]['items']);
                    /* VIII.2 carries its own a.-e. list. */
                    $this->assertSame([], $viii['blocks'][0]['items'][0]['lettered']);
                    $this->assertCount(5, $viii['blocks'][0]['items'][1]['lettered']);
                    $this->assertSame('Cause(s) of the breach incident', $viii['blocks'][0]['items'][1]['lettered'][0]);

                    $ix = $sections->firstWhere('numeral', 'IX');
                    $this->assertCount(3, $ix['blocks'][0]['items']);
                    $this->assertStringStartsWith('**Termination:**', $ix['blocks'][0]['items'][1]['text']);

                    /* Additions continue the numbering in every open section. */
                    foreach (['IV', 'V', 'VI', 'VIII', 'IX'] as $numeral) {
                        $this->assertSame('numbered', $sections->firstWhere('numeral', $numeral)['addition']['as']);
                    }
                    $this->assertSame('services', $sections->firstWhere('numeral', 'VII')['addition']['as']);

                    return true;
                }));
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

    /**
     * CA1 is the client account's name: not the company name, not the
     * profile's representative field. The poster of the project, or the
     * business's owner account once the poster is gone.
     */
    public function test_ca1_is_the_client_account_name(): void
    {
        [$owner, , $agreement] = $this->agreement();

        $this->assertSame('Northwind Trading', $owner->currentTeam->clientProfile->business_name);
        $this->assertSame('Maria Santos', $owner->currentTeam->clientProfile->owner_name);

        $this->actingAs($owner)
            ->get($this->url('agreements.contract', $owner, $agreement))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement.moa.ca1', $owner->name)
                ->where('agreement.moa.ca1Representative', $owner->name));

        /* A teammate posted it: their account is the representative. */
        $poster = User::factory()->client()->create(['name' => 'Rafael Cruz']);
        $agreement->project->update(['created_by' => $poster->id]);

        $this->actingAs($owner)
            ->get($this->url('agreements.contract', $owner, $agreement))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement.moa.ca1', 'Rafael Cruz'));

        /* The poster's account is gone: the business's owner account. */
        $agreement->project->update(['created_by' => null]);

        $this->actingAs($owner)
            ->get($this->url('agreements.contract', $owner, $agreement))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement.moa.ca1', $owner->name));
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
                ->where('agreement.moa.sections.3.entries.0.title', null)
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
     * Section VII never adds a phase: its services are kept with the other
     * additions, and the phases stay Objective, Scope and Turnover.
     */
    public function test_a_service_is_kept_in_section_vii_and_never_adds_a_phase(): void
    {
        [$owner, $student, $agreement] = $this->agreement();

        $this->addService($owner, $agreement, 'Inventory module', 'Stock, suppliers and reorder alerts.');
        $this->addService($student, $agreement, 'Reports module', 'Monthly sales and stock reports.');

        $this->assertSame(['Objective', 'Scope', 'Turnover'], $agreement->refresh()->milestones->pluck('title')->all());

        $services = $agreement->services()->get();
        $this->assertSame(['Inventory module', 'Reports module'], $services->pluck('title')->all());
        $this->assertSame(['Stock, suppliers and reorder alerts.', 'Monthly sales and stock reports.'], $services->pluck('body')->all());
        $this->assertSame([$owner->id, $student->id], $services->pluck('user_id')->all());

        $this->actingAs($student)
            ->get($this->url('agreements.contract', $student, $agreement))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('agreement.moa.sections.6.entries', 2)
                ->where('agreement.moa.sections.6.entries.0.title', 'Inventory module')
                ->where('agreement.moa.sections.6.entries.0.body', 'Stock, suppliers and reorder alerts.')
                ->where('agreement.moa.sections.6.entries.0.canChange', false)
                ->where('agreement.moa.sections.6.entries.1.canChange', true));

        $this->actingAs($owner)
            ->delete($this->url('agreements.services.destroy', $owner, $agreement, ['service' => $services->first()]))
            ->assertRedirect();

        $this->assertSame(['Reports module'], $agreement->services()->pluck('title')->all());
        $this->assertSame(3, $agreement->milestones()->count());
    }

    public function test_only_the_author_changes_a_service(): void
    {
        [$owner, $student, $agreement] = $this->agreement();

        $this->addService($owner, $agreement, 'Inventory module', 'Stock and suppliers.');
        $service = $agreement->services()->firstOrFail();

        $this->actingAs($student)
            ->patch($this->url('agreements.services.update', $student, $agreement, ['service' => $service]), ['objective' => 'Hijacked', 'scope' => 'x'])
            ->assertForbidden();
        $this->actingAs($student)
            ->delete($this->url('agreements.services.destroy', $student, $agreement, ['service' => $service]))
            ->assertForbidden();

        $this->actingAs($owner)
            ->patch($this->url('agreements.services.update', $owner, $agreement, ['service' => $service]), ['objective' => 'Inventory and suppliers', 'scope' => 'Stock, suppliers and purchase orders.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Inventory and suppliers', $service->fresh()->title);
        $this->assertSame('Stock, suppliers and purchase orders.', $service->fresh()->body);
    }

    /**
     * The services routes only reach Section VII: a line in another section,
     * even the user's own, is not a service.
     */
    public function test_the_services_routes_only_reach_section_vii(): void
    {
        [$owner, , $agreement] = $this->agreement();
        $this->addRequirement($owner, $agreement, MemorandumSection::Joint, 'Meet every Friday.');
        $line = AgreementRequirement::query()->firstOrFail();

        $this->actingAs($owner)
            ->patch($this->url('agreements.services.update', $owner, $agreement, ['service' => $line]), ['objective' => 'x', 'scope' => 'y'])
            ->assertForbidden();
        $this->actingAs($owner)
            ->delete($this->url('agreements.services.destroy', $owner, $agreement, ['service' => $line]))
            ->assertNotFound();

        $this->assertSame('Meet every Friday.', $line->fresh()->body);
        $this->assertNull($line->fresh()->title);
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

        $this->actingAs($owner)
            ->post($this->url('agreements.services.store', $owner, $agreement), ['objective' => str_repeat('a', 121), 'scope' => str_repeat('b', 2001)])
            ->assertSessionHasErrors(['objective', 'scope']);

        $this->assertSame(0, $agreement->services()->count());
    }

    /**
     * Section VII is required: with no service in it, neither the client nor
     * the student may sign, and both screens say why before anyone tries.
     */
    public function test_neither_party_can_sign_while_section_vii_is_empty(): void
    {
        [$owner, $student, $agreement] = $this->agreement();
        $this->datePhases($agreement);

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

        /* One service, and both may sign: the second signature starts the work. */
        $this->addService($student, $agreement, 'Inventory module', 'Stock and suppliers.');

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

    public function test_a_service_without_its_objective_or_scope_blocks_signing(): void
    {
        [$owner, , $agreement] = $this->agreement();
        $this->datePhases($agreement);

        /* Carried over from the first SDPC version with no scope written. */
        $agreement->requirements()->create(['section' => MemorandumSection::Services, 'title' => 'Design', 'body' => '']);

        $this->actingAs($owner)
            ->post($this->url('agreements.signatures.store', $owner, $agreement), $this->signature($owner->name))
            ->assertSessionHasErrors(['signed_name' => 'Every service in Section VII needs its objective and its scope before this agreement can be signed.']);
    }

    public function test_every_phase_needs_dates_and_turnover_a_month_before_signing(): void
    {
        [$owner, , $agreement] = $this->agreement();
        $this->addService($owner, $agreement, 'Inventory module', 'Stock and suppliers.');

        $this->actingAs($owner)
            ->post($this->url('agreements.signatures.store', $owner, $agreement), $this->signature($owner->name))
            ->assertSessionHasErrors(['signed_name' => 'Every milestone needs an end date before this agreement can be signed.']);

        $this->datePhases($agreement);
        $agreement->refresh()->turnoverPhase()->update([
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
        $this->datePhases($agreement);

        $requirement = $agreement->requirements()->where('section', MemorandumSection::Joint)->firstOrFail();
        $service = $agreement->services()->firstOrFail();

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
                ->where('agreement.moa.sections.6.entries.0.canChange', false)
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

        $this->assertSame(['Objective', 'Scope', 'Turnover'], $successor->milestones->pluck('title')->all());

        $service = $successor->services()->firstOrFail();
        $this->assertSame('Inventory module', $service->title);
        $this->assertSame('Stock and suppliers.', $service->body);
        $this->assertSame($owner->id, $service->user_id);

        $line = $successor->requirements()->where('section', MemorandumSection::ContractingAgency)->firstOrFail();
        $this->assertSame('Provide test data.', $line->body);
        $this->assertSame($student->id, $line->user_id);
    }

    /**
     * When the work starts, every objective lands in the Objective phase and
     * every scope in the Scope phase: two separate tasks, never one, and
     * never a phase of their own.
     */
    public function test_project_management_gets_each_objective_and_each_scope_separately(): void
    {
        [, $student, $agreement] = $this->signedAgreement();

        $this->actingAs($student)
            ->get(route('project-management', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('agreement.phases', 3)
                ->where('agreement.phases.0.title', 'Objective')
                ->where('agreement.phases.1.title', 'Scope')
                ->where('agreement.phases.2.title', 'Turnover')
                ->where('agreement.phases.2.isTurnover', true)
                ->has('agreement.phases.0.tasks', 2)
                ->where('agreement.phases.0.tasks.0.title', 'Inventory module')
                ->where('agreement.phases.0.tasks.1.title', 'Reports module')
                ->has('agreement.phases.1.tasks', 2)
                ->where('agreement.phases.1.tasks.0.title', 'Stock and suppliers.')
                ->where('agreement.phases.1.tasks.1.title', 'Monthly reports.')
                ->has('agreement.phases.2.tasks', 0)
                ->where('agreement.phases.0.tasks.0.status', 'open'));
    }

    /**
     * The tasks are seeded once, by the second signature: the first changes
     * nothing in the phases, and an agreement in an earlier wording gets none.
     */
    public function test_the_tasks_are_seeded_once_and_only_for_the_sdpc_memorandum(): void
    {
        [$owner, $student, $agreement] = $this->agreement();
        $this->addService($owner, $agreement, 'Inventory module', 'Stock and suppliers.');
        $this->datePhases($agreement);

        $this->actingAs($owner)
            ->post($this->url('agreements.signatures.store', $owner, $agreement), $this->signature($owner->name))
            ->assertSessionHasNoErrors();
        $this->assertSame(0, AgreementTask::query()->count());

        $this->actingAs($student)
            ->post($this->url('agreements.signatures.store', $student, $agreement), $this->signature($student->name))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, AgreementTask::query()->count());

        /* Signing again is refused, so nothing is seeded twice. */
        $this->actingAs($student)
            ->post($this->url('agreements.signatures.store', $student, $agreement), $this->signature($student->name))
            ->assertForbidden();
        $this->assertSame(2, AgreementTask::query()->count());

        /* The earlier wording has no Section VII: neither services nor seeded tasks. */
        [$owner, $student, $earlier] = $this->agreement();
        $earlier->update(['template' => AgreementTemplate::Memorandum]);

        $this->actingAs($owner)
            ->post($this->url('agreements.services.store', $owner, $earlier), ['objective' => 'x', 'scope' => 'y'])
            ->assertForbidden();

        $earlier->requirements()->create(['section' => MemorandumSection::Services, 'title' => 'Stray', 'body' => 'Stray']);
        $this->datePhases($earlier);
        $this->signBoth($owner, $student, $earlier);

        $this->assertSame(AgreementStatus::Active, $earlier->fresh()->status);
        $this->assertSame(0, $earlier->fresh()->taskCount() + $earlier->fresh()->turnoverPhase()->tasks()->count());
    }

    /**
     * A long scope arrives whole, and the student can still give its task a
     * first deadline without the title being refused.
     */
    public function test_a_long_scope_arrives_whole_and_its_task_can_be_edited(): void
    {
        $scope = 'To address the difficulty of client acquisition of tertiary students, the system will feature a dedicated platform where students and local clients can connect directly minimizing the need for relying on personal referrals and traditional client developer acquisition methods.';
        $this->assertGreaterThan(255, strlen($scope));

        [$owner, $student, $agreement] = $this->agreement();
        $this->addService($owner, $agreement, 'To design and develop a system that addresses the difficulty of client acquisition of tertiary students', $scope);
        $this->datePhases($agreement);
        $this->signBoth($owner, $student, $agreement);

        $task = $agreement->fresh()->milestones[1]->tasks()->firstOrFail();
        $this->assertSame($scope, $task->title);

        $this->actingAs($student)
            ->patch(route('agreements.tasks.update', ['current_team' => $student->currentTeam, 'agreement' => $agreement, 'task' => $task]), [
                'title' => $scope,
                'due_on' => now()->addWeek()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(now()->addWeek()->toDateString(), $task->fresh()->due_on->toDateString());
    }

    /**
     * Turnover's tasks count for nothing in the percentage, on every screen
     * that reads it.
     */
    public function test_turnover_is_left_out_of_the_progress_figure(): void
    {
        [$owner, , $agreement] = $this->signedAgreement();
        [$objective, , $turnover] = $agreement->milestones;

        /* The four seeded tasks (two objectives, two scopes): one verified. */
        $objective->tasks()->first()->update(['status' => TaskStatus::Verified]);
        /* Turnover: one verified, two open. None of them may move the figure. */
        AgreementTask::factory()->create(['agreement_milestone_id' => $turnover->id, 'status' => TaskStatus::Verified]);
        AgreementTask::factory()->count(2)->create(['agreement_milestone_id' => $turnover->id, 'status' => TaskStatus::Open]);

        $agreement = $agreement->fresh();

        $this->assertSame(25, $agreement->progress());
        $this->assertSame(4, $agreement->taskCount());
        $this->assertSame(1, $agreement->verifiedTaskCount());

        $summary = app(SummariseProgress::class)->handle($agreement);
        $this->assertSame(25, $summary['progress']);
        $this->assertSame(4, $summary['taskCount']);
        $this->assertTrue($summary['phases'][2]['isTurnover']);
        $this->assertFalse($summary['phases'][0]['isTurnover']);

        $this->actingAs($owner)
            ->get(route('project-management', ['current_team' => $owner->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement.summary.progress', 25)
                ->where('agreement.summary.taskCount', 4)
                ->where('agreement.summary.verifiedCount', 1));
    }

    /**
     * The first SDPC version made every service a phase. Open agreements, and
     * active ones with no task written yet, move to Objective, Scope and
     * Turnover with their services kept in Section VII; anything with work
     * already tracked, or on another wording, is left alone.
     */
    public function test_the_migration_moves_service_phases_into_objective_and_scope(): void
    {
        $migration = require database_path('migrations/2026_10_03_134324_move_section_vii_services_into_objective_and_scope.php');
        $migration->down();

        [$owner, $student, $draft] = $this->agreement();
        [, , $active] = $this->agreement();
        [, , $worked] = $this->agreement();
        [, , $clauses] = $this->agreement();

        foreach ([$draft, $active, $worked, $clauses] as $agreement) {
            $agreement->milestones()->delete();
            DB::table('agreement_milestones')->insert([
                $this->phaseRow($agreement, 1, 'Inventory module', 'Stock and suppliers.', $owner->id, '2026-11-02', '2026-11-20'),
                $this->phaseRow($agreement, 2, 'Reports module', 'Monthly reports.', $student->id, '2026-11-10', '2026-12-01'),
                $this->phaseRow($agreement, 3, 'Turnover', null, null, '2026-12-02', '2027-01-05'),
            ]);
        }

        $active->update(['status' => AgreementStatus::Active]);
        $worked->update(['status' => AgreementStatus::Active]);
        AgreementTask::factory()->create(['agreement_milestone_id' => $worked->milestones()->first()->id]);
        $clauses->update(['template' => AgreementTemplate::Clauses]);

        $migration->up();

        foreach ([$draft, $active] as $agreement) {
            $phases = $agreement->fresh()->milestones;
            $this->assertSame(['Objective', 'Scope', 'Turnover'], $phases->pluck('title')->all());
            $this->assertSame([1, 2, 3], $phases->pluck('position')->all());
            /* The two phases span the services' dates; Turnover keeps its own. */
            $this->assertSame('2026-11-02', $phases[0]->starts_on->toDateString());
            $this->assertSame('2026-12-01', $phases[1]->ends_on->toDateString());
            $this->assertSame('2027-01-05', $phases[2]->ends_on->toDateString());

            $services = $agreement->services()->get();
            $this->assertSame(['Inventory module', 'Reports module'], $services->pluck('title')->all());
            $this->assertSame(['Stock and suppliers.', 'Monthly reports.'], $services->pluck('body')->all());
            $this->assertSame([$owner->id, $student->id], $services->pluck('user_id')->all());
        }

        /* Only the active one gets its tasks: objectives in Objective, scopes in Scope. */
        $this->assertSame(0, $draft->fresh()->taskCount());
        [$objective, $scope] = $active->fresh()->milestones;
        $this->assertSame(['Inventory module', 'Reports module'], $objective->tasks()->pluck('title')->all());
        $this->assertSame(['Stock and suppliers.', 'Monthly reports.'], $scope->tasks()->pluck('title')->all());

        foreach ([$worked, $clauses] as $untouched) {
            $this->assertSame(['Inventory module', 'Reports module', 'Turnover'], $untouched->fresh()->milestones->pluck('title')->all());
            $this->assertSame(0, $untouched->services()->count());
        }

    }

    /**
     * A drafted agreement between Northwind Trading (profile representative
     * Maria Santos) and a student, on a posting the owner account posted.
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
            'created_by' => $owner->id,
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
     * Two services, dated phases, and both signatures: an active agreement.
     *
     * @return array{0: User, 1: User, 2: Agreement}
     */
    private function signedAgreement(): array
    {
        [$owner, $student, $agreement] = $this->agreement();

        $this->addService($owner, $agreement, 'Inventory module', 'Stock and suppliers.');
        $this->addService($student, $agreement, 'Reports module', 'Monthly reports.');
        $this->datePhases($agreement);
        $this->signBoth($owner, $student, $agreement);

        return [$owner, $student, $agreement->fresh()->load('milestones')];
    }

    private function signBoth(User $owner, User $student, Agreement $agreement): void
    {
        foreach ([$owner, $student] as $party) {
            $this->actingAs($party)
                ->post($this->url('agreements.signatures.store', $party, $agreement), $this->signature($party->name))
                ->assertSessionHasNoErrors();
        }
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
     * Objective and Scope overlap; Turnover follows them for a month.
     */
    private function datePhases(Agreement $agreement): void
    {
        [$objective, $scope, $turnover] = $agreement->refresh()->milestones;

        $objective->update(['starts_on' => now()->toDateString(), 'ends_on' => now()->addWeeks(2)->toDateString()]);
        $scope->update(['starts_on' => now()->addWeek()->toDateString(), 'ends_on' => now()->addWeeks(2)->toDateString()]);
        $turnover->update(['starts_on' => now()->addWeeks(3)->toDateString(), 'ends_on' => now()->addWeeks(3)->addMonth()->toDateString()]);
    }

    /**
     * One agreement_milestones row as the first SDPC version wrote it.
     *
     * @return array<string, mixed>
     */
    private function phaseRow(Agreement $agreement, int $position, string $title, ?string $description, ?int $addedBy, string $startsOn, string $endsOn): array
    {
        return [
            'agreement_id' => $agreement->id,
            'position' => $position,
            'title' => $title,
            'description' => $description,
            'added_by' => $addedBy,
            'amount' => 0,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ];
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
