<?php

namespace Tests\Feature\Agreements;

use App\Actions\Notifications\PresentNotification;
use App\Enums\AddendumStatus;
use App\Enums\AgreementParty;
use App\Enums\AgreementStatus;
use App\Enums\TaskStatus;
use App\Models\Addendum;
use App\Models\AddendumService;
use App\Models\Agreement;
use App\Models\AgreementTask;
use App\Models\User;
use App\Notifications\Agreements\AddendumExecuted;
use App\Notifications\Agreements\AddendumSigned;
use App\Notifications\Agreements\ProjectExtensionRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Agreements\Concerns\StartsCollaboration;
use Tests\TestCase;

/**
 * The Payment & Project Extension Addendum: asking for it at 80%, filling in
 * Section II and Section IV together, and signing it once both GCash accounts
 * are registered (owner, 2026-10-10).
 */
class ProjectExtensionTest extends TestCase
{
    use RefreshDatabase, StartsCollaboration;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_the_client_opens_an_extension_once_the_build_is_eighty_percent_done(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaborationAt(80);

        $response = $this->actingAs($client)
            ->post(route('agreements.addenda.store', $this->asParty($client, $agreement)));

        $addendum = Addendum::query()->sole();

        $response->assertRedirect(route('agreements.addenda.show', $this->asParty($client, $agreement, ['addendum' => $addendum])));
        $this->assertSame(AddendumStatus::Draft, $addendum->status);
        $this->assertSame($agreement->reference.'-A1', $addendum->reference);
        $this->assertSame(AgreementStatus::Active, $agreement->refresh()->status);

        Notification::assertSentTo($student, ProjectExtensionRequested::class);
    }

    public function test_an_extension_is_refused_below_the_threshold(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaborationAt(75);

        $this->actingAs($client)
            ->post(route('agreements.addenda.store', $this->asParty($client, $agreement)))
            ->assertSessionHasErrors(['extension' => 'A project can be extended once it is at least 80% complete. This one is at 75%.']);

        $this->assertDatabaseCount('addenda', 0);
    }

    public function test_only_one_extension_is_open_at_a_time_and_a_cancelled_one_frees_the_slot(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaborationAt(100);

        $this->actingAs($client)->post(route('agreements.addenda.store', $this->asParty($client, $agreement)));

        $this->actingAs($client)
            ->post(route('agreements.addenda.store', $this->asParty($client, $agreement)))
            ->assertSessionHasErrors('extension');

        $first = Addendum::query()->sole();

        $this->actingAs($client)
            ->delete(route('agreements.addenda.destroy', $this->asParty($client, $agreement, ['addendum' => $first])))
            ->assertRedirect();

        $this->assertSame(AddendumStatus::Cancelled, $first->refresh()->status);

        $this->actingAs($client)->post(route('agreements.addenda.store', $this->asParty($client, $agreement)));

        $this->assertSame($agreement->reference.'-A2', Addendum::query()->latest('id')->first()->reference);
    }

    public function test_the_student_cannot_ask_for_an_extension(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaborationAt(100);

        $this->actingAs($student)
            ->post(route('agreements.addenda.store', $this->asParty($student, $agreement)))
            ->assertForbidden();
    }

    public function test_both_parties_add_to_section_two_and_only_the_author_changes_an_entry(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement, 'addendum' => $addendum] = $this->draftAddendum();

        foreach ([$client, $student] as $party) {
            $this->actingAs($party)
                ->post(route('agreements.addenda.services.store', $this->asParty($party, $agreement, ['addendum' => $addendum])), [
                    'objective' => 'Feature Enhancements by '.$party->name,
                    'scope' => 'Implement the supplementary modules.',
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, $addendum->services()->count());

        $clientsEntry = $addendum->services()->where('user_id', $client->id)->sole();

        $this->actingAs($student)
            ->patch(route('agreements.addenda.services.update', $this->asParty($student, $agreement, ['addendum' => $addendum, 'service' => $clientsEntry])), [
                'objective' => 'Hijacked',
                'scope' => 'Not mine to change.',
            ])
            ->assertForbidden();

        $this->actingAs($student)
            ->delete(route('agreements.addenda.services.destroy', $this->asParty($student, $agreement, ['addendum' => $addendum, 'service' => $clientsEntry])))
            ->assertForbidden();
    }

    public function test_the_target_amount_takes_whole_pesos_up_to_twenty_thousand(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement, 'addendum' => $addendum] = $this->draftAddendum();

        $url = route('agreements.addenda.update', $this->asParty($client, $agreement, ['addendum' => $addendum]));

        $this->actingAs($client)->patch($url, ['total_amount' => 20001])->assertSessionHasErrors('total_amount');
        $this->actingAs($client)->patch($url, ['total_amount' => 99])->assertSessionHasErrors('total_amount');
        $this->actingAs($client)->patch($url, ['total_amount' => 'a lot'])->assertSessionHasErrors('total_amount');

        $this->actingAs($student)
            ->patch(route('agreements.addenda.update', $this->asParty($student, $agreement, ['addendum' => $addendum])), ['total_amount' => 20000])
            ->assertSessionHasNoErrors();

        $this->assertSame(20000, $addendum->refresh()->total_amount);
    }

    public function test_nobody_signs_until_section_two_section_four_and_both_gcash_accounts_are_in(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement, 'addendum' => $addendum] = $this->draftAddendum();

        $sign = fn (User $party) => $this->actingAs($party)->post(
            route('agreements.addenda.signatures.store', $this->asParty($party, $agreement, ['addendum' => $addendum])),
            ['signed_name' => $party->name, 'agreed' => true],
        );

        $sign($client)->assertSessionHasErrors(['signed_name' => 'Section II. Purpose & Scope of Extension needs at least one service (an objective and its scope) before this addendum can be signed.']);

        AddendumService::factory()->create(['addendum_id' => $addendum->id, 'user_id' => $client->id]);
        $sign($client)->assertSessionHasErrors('signed_name');

        $addendum->update(['total_amount' => 10000]);
        $sign($client)->assertSessionHasErrors(['signed_name' => 'The student has not registered a GCash account in Settings yet. Both parties need one before this addendum can be signed.']);

        $student->forceFill(['gcash_number' => '09281234567'])->save();
        $sign($student)->assertSessionHasErrors(['signed_name' => 'The client has not registered a GCash account in Settings yet. Both parties need one before this addendum can be signed.']);

        $this->assertNull($addendum->refresh()->student_signed_at);
    }

    public function test_the_second_signature_executes_it_with_a_thirty_seventy_split(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement, 'addendum' => $addendum] = $this->readyToSign(10001);

        $this->actingAs($client)
            ->post(route('agreements.addenda.signatures.store', $this->asParty($client, $agreement, ['addendum' => $addendum])), [
                'signed_name' => 'Clara Client',
                'agreed' => true,
            ])
            ->assertSessionHasNoErrors();

        $addendum->refresh();
        $this->assertSame(AddendumStatus::AwaitingSignatures, $addendum->status);
        Notification::assertSentTo($student, AddendumSigned::class);

        /* Terms close the moment anybody signs. */
        $this->actingAs($student)
            ->post(route('agreements.addenda.services.store', $this->asParty($student, $agreement, ['addendum' => $addendum])), [
                'objective' => 'Late addition',
                'scope' => 'Too late.',
            ])
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('agreements.addenda.signatures.store', $this->asParty($student, $agreement, ['addendum' => $addendum])), [
                'signed_name' => 'Sam Student',
                'agreed' => true,
            ])
            ->assertSessionHasNoErrors();

        $addendum->refresh()->load('payments');
        $this->assertSame(AddendumStatus::Active, $addendum->status);
        $this->assertNotNull($addendum->executed_at);
        $this->assertSame([300030, 700070], $addendum->payments->pluck('amount')->all());
        $this->assertSame([30, 70], $addendum->payments->pluck('percentage')->all());
        $this->assertSame('INV-'.$addendum->reference.'-M1', $addendum->payment(1)->invoice_number);

        /* Each signature keeps the account it was given with, encrypted at rest. */
        $this->assertSame('09281234567', $addendum->student_gcash_number);
        $this->assertSame('09171234567', $addendum->client_gcash_number);
        $this->assertStringNotContainsString('0917', (string) DB::table('addenda')->value('client_gcash_number'));

        Notification::assertSentTo([$client, $student], AddendumExecuted::class);
    }

    public function test_a_party_signs_only_once_and_needs_to_confirm(): void
    {
        ['client' => $client, 'agreement' => $agreement, 'addendum' => $addendum] = $this->readyToSign();

        $url = route('agreements.addenda.signatures.store', $this->asParty($client, $agreement, ['addendum' => $addendum]));

        $this->actingAs($client)->post($url, ['signed_name' => 'Clara'])->assertSessionHasErrors('agreed');
        $this->actingAs($client)->post($url, ['signed_name' => 'Clara', 'agreed' => true])->assertSessionHasNoErrors();
        $this->actingAs($client)->post($url, ['signed_name' => 'Clara', 'agreed' => true])->assertForbidden();
    }

    public function test_the_screen_shows_gcash_masked_and_never_the_number(): void
    {
        ['client' => $client, 'agreement' => $agreement, 'addendum' => $addendum] = $this->readyToSign();

        $response = $this->actingAs($client)
            ->get(route('agreements.addenda.show', $this->asParty($client, $agreement, ['addendum' => $addendum])));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('agreements/addendum')
            ->where('addendum.document.gcash.student', '09******567')
            ->where('addendum.document.gcash.client', '09******567')
            ->where('addendum.viewer.canEdit', true)
            ->where('addendum.viewer.signingBlockedBy', null)
            ->where('addendum.document.ca1', $agreement->student->name)
            ->where('addendum.document.services', $agreement->project->title));

        $this->assertStringNotContainsString('09171234567', $response->getContent());
        $this->assertStringNotContainsString('09281234567', $response->getContent());
    }

    public function test_the_printable_copy_the_records_and_the_blank_form_are_open_to_both_parties_only(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement, 'addendum' => $addendum] = $this->readyToSign();

        foreach ([$client, $student] as $party) {
            $args = $this->asParty($party, $agreement, ['addendum' => $addendum]);

            $this->actingAs($party)->get(route('agreements.addenda.printable', $args))
                ->assertInertia(fn (Assert $page) => $page->component('agreements/addendum-printable'));
            $this->actingAs($party)->get(route('agreements.addenda.records', $args))
                ->assertInertia(fn (Assert $page) => $page->component('agreements/addendum-records'));
            $this->actingAs($party)->get(route('agreements.addenda.template', $args))
                ->assertOk()
                ->assertDownload($addendum->reference.' Addendum.pdf');
        }

        $outsider = User::factory()->student()->create();

        $this->actingAs($outsider)
            ->get(route('agreements.addenda.show', ['current_team' => $outsider->currentTeam, 'agreement' => $agreement, 'addendum' => $addendum]))
            ->assertForbidden();
    }

    public function test_the_agreement_screen_lists_its_addenda(): void
    {
        ['student' => $student, 'agreement' => $agreement, 'addendum' => $addendum] = $this->draftAddendum();

        $this->actingAs($student)
            ->get(route('agreements.show', $this->asParty($student, $agreement)))
            ->assertInertia(fn (Assert $page) => $page
                ->where('addenda.0.reference', $addendum->reference)
                ->where('addenda.0.statusLabel', 'Draft'));
    }

    public function test_project_management_offers_the_button_from_the_threshold_and_tells_the_student(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaborationAt(75);

        $this->actingAs($client)
            ->get(route('project-management', ['current_team' => $client->currentTeam, 'agreement' => $agreement->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('agreement.extension.canRequest', false)
                ->where('agreement.extension.progress', 75));

        AgreementTask::query()->where('status', TaskStatus::Open)->update(['status' => TaskStatus::Verified]);

        $this->actingAs($client)
            ->get(route('project-management', ['current_team' => $client->currentTeam, 'agreement' => $agreement->id]))
            ->assertInertia(fn (Assert $page) => $page->where('agreement.extension.canRequest', true));

        $this->actingAs($client)->post(route('agreements.addenda.store', $this->asParty($client, $agreement)));

        $this->actingAs($student)
            ->get(route('project-management', ['current_team' => $student->currentTeam, 'agreement' => $agreement->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('agreement.extension.canRequest', false)
                ->where('agreement.extension.open.status', 'draft'));
    }

    public function test_every_addendum_notification_reads_as_a_line_with_a_link(): void
    {
        ['student' => $student, 'addendum' => $addendum] = $this->draftAddendum();

        foreach ([
            new ProjectExtensionRequested($addendum),
            new AddendumSigned($addendum, AgreementParty::Client),
            new AddendumExecuted($addendum),
        ] as $notification) {
            /* Notifications are faked here, so the stored row is written as the database channel would. */
            $student->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $notification::class,
                'data' => $notification->toArray($student),
            ]);
        }

        $notifications = $student->notifications()->get();
        $this->assertCount(3, $notifications);

        foreach ($notifications as $notification) {
            $row = app(PresentNotification::class)->handle($notification, $student->currentTeam);

            $this->assertStringContainsString('/addenda/'.$addendum->id, (string) $row['url']);
        }
    }

    /**
     * A collaboration whose build is at the given progress: ten tasks in the
     * first phase, that many tenths verified.
     *
     * @return array{client: User, student: User, agreement: Agreement}
     */
    private function collaborationAt(int $percent): array
    {
        $parties = $this->collaboration();
        $phase = $parties['agreement']->milestones->first();
        $verified = intdiv($percent, 10);

        foreach (range(1, 20) as $position) {
            AgreementTask::factory()->create([
                'agreement_milestone_id' => $phase->id,
                'position' => $position,
                'status' => $position <= $verified * 2 ? TaskStatus::Verified : TaskStatus::Open,
            ]);
        }

        if ($percent % 10 !== 0) {
            AgreementTask::query()->where('agreement_milestone_id', $phase->id)
                ->where('position', $verified * 2 + 1)
                ->update(['status' => TaskStatus::Verified]);
        }

        return $parties;
    }

    /**
     * @return array{client: User, student: User, agreement: Agreement, addendum: Addendum}
     */
    private function draftAddendum(): array
    {
        $parties = $this->collaborationAt(100);

        $addendum = Addendum::factory()->create([
            'agreement_id' => $parties['agreement']->id,
            'reference' => $parties['agreement']->reference.'-A1',
            'requested_by' => $parties['client']->id,
        ]);

        return [...$parties, 'addendum' => $addendum];
    }

    /**
     * A draft with a service, an amount and both GCash accounts registered.
     *
     * @return array{client: User, student: User, agreement: Agreement, addendum: Addendum}
     */
    private function readyToSign(int $pesos = 10000): array
    {
        $parties = $this->draftAddendum();

        AddendumService::factory()->create([
            'addendum_id' => $parties['addendum']->id,
            'user_id' => $parties['client']->id,
            'objective' => 'Post-Deployment Maintenance',
            'scope' => 'Routine bug fixes and dependency updates.',
        ]);

        $parties['addendum']->update(['total_amount' => $pesos]);
        $parties['client']->forceFill(['gcash_number' => '09171234567'])->save();
        $parties['student']->forceFill(['gcash_number' => '09281234567'])->save();

        return $parties;
    }
}
