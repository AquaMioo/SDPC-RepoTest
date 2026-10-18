<?php

namespace Tests\Feature\Agreements;

use App\Actions\Notifications\PresentNotification;
use App\Enums\AddendumStatus;
use App\Enums\AgreementStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ContractRequestStatus;
use App\Enums\ProjectStatus;
use App\Models\Addendum;
use App\Models\Agreement;
use App\Models\ContractRequest;
use App\Models\ProjectRating;
use App\Models\User;
use App\Notifications\Agreements\ContractRequestUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Agreements\Concerns\StartsCollaboration;
use Tests\TestCase;

/**
 * Ending a contract (owner, 2026-10-18). A denial of an unsigned MoA or
 * Addendum is final the moment it is sent: nobody declines it, denies it back
 * or withdraws it. A termination of a running collaboration is asked with a
 * reason and carried out only once the other side accepts.
 */
class ContractRequestTest extends TestCase
{
    use RefreshDatabase, StartsCollaboration;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_a_moa_denial_is_final_and_frees_both_sides_at_once(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration(AgreementStatus::Draft);
        $agreement->project->update(['status' => ProjectStatus::Open]);

        $this->assertTrue($student->isLockedToProject());

        $this->actingAs($student)
            ->post(route('agreements.denials.store', $this->asParty($student, $agreement)), ['reason' => 'The schedule clashes with my exams.'])
            ->assertSessionHasNoErrors();

        $request = ContractRequest::query()->sole();
        $this->assertSame(ContractRequestStatus::Denied, $request->status);
        $this->assertSame($student->id, $request->decided_by);
        $this->assertSame(AgreementStatus::Cancelled, $agreement->refresh()->status);
        $this->assertSame(ApplicationStatus::Withdrawn, $agreement->application->refresh()->status);
        $this->assertFalse($student->fresh()->isLockedToProject());
        $this->assertSame(ProjectStatus::Open, $agreement->project->refresh()->status);
        Notification::assertSentTo(
            [$client, $student],
            ContractRequestUpdated::class,
            fn (ContractRequestUpdated $notification): bool => $notification->event === 'denied',
        );
    }

    public function test_a_denial_needs_a_reason(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->collaboration(AgreementStatus::Draft);

        $this->actingAs($client)
            ->post(route('agreements.denials.store', $this->asParty($client, $agreement)), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, ContractRequest::query()->count());
        $this->assertSame(AgreementStatus::Draft, $agreement->refresh()->status);
    }

    public function test_a_denial_cannot_be_declined_accepted_withdrawn_or_denied_back(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration(AgreementStatus::Draft);

        $this->actingAs($client)
            ->post(route('agreements.denials.store', $this->asParty($client, $agreement)), ['reason' => 'We found the budget is not there.']);
        $request = ContractRequest::query()->sole();

        $this->actingAs($student)
            ->post(route('agreements.requests.decline', $this->asParty($student, $agreement, ['contractRequest' => $request])), ['note' => 'Let us talk first.'])
            ->assertForbidden();
        $this->actingAs($student)
            ->post(route('agreements.requests.accept', $this->asParty($student, $agreement, ['contractRequest' => $request])))
            ->assertForbidden();
        $this->actingAs($client)
            ->delete(route('agreements.requests.destroy', $this->asParty($client, $agreement, ['contractRequest' => $request])))
            ->assertForbidden();
        $this->actingAs($student)
            ->post(route('agreements.denials.store', $this->asParty($student, $agreement)), ['reason' => 'I deny your denial.'])
            ->assertForbidden();

        $this->assertSame(ContractRequestStatus::Denied, $request->refresh()->status);
        $this->assertSame(1, ContractRequest::query()->count());
        $this->assertSame(AgreementStatus::Cancelled, $agreement->refresh()->status);
        $this->assertSame(ApplicationStatus::Rejected, $agreement->application->refresh()->status);
        $this->assertFalse(Gate::forUser($student)->allows('sign', $agreement));
    }

    public function test_both_sides_read_the_final_denial_on_the_agreement(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration(AgreementStatus::Draft);

        $this->actingAs($client)
            ->post(route('agreements.denials.store', $this->asParty($client, $agreement)), ['reason' => 'We found the budget is not there.']);

        $this->actingAs($student)
            ->get(route('agreements.show', $this->asParty($student, $agreement)))
            ->assertInertia(fn (Assert $page) => $page
                ->where('agreement.denial.status', 'denied')
                ->where('agreement.denial.reason', 'We found the budget is not there.')
                ->where('agreement.denial.byViewer', false)
                ->where('agreement.denial.canDecide', false)
                ->where('agreement.denial.canWithdraw', false));

        $this->actingAs($client)
            ->get(route('agreements.show', $this->asParty($client, $agreement)))
            ->assertInertia(fn (Assert $page) => $page
                ->where('agreement.denial.byViewer', true)
                ->where('agreement.denial.canWithdraw', false));
    }

    public function test_the_side_that_asked_to_terminate_may_withdraw(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();

        $this->actingAs($student)
            ->post(route('agreements.terminations.store', $this->asParty($student, $agreement)), ['reason' => 'I am transferring schools.']);
        $request = ContractRequest::query()->sole();

        $this->actingAs($client)
            ->delete(route('agreements.requests.destroy', $this->asParty($client, $agreement, ['contractRequest' => $request])))
            ->assertForbidden();

        $this->actingAs($student)
            ->delete(route('agreements.requests.destroy', $this->asParty($student, $agreement, ['contractRequest' => $request])))
            ->assertRedirect();

        $this->assertSame(ContractRequestStatus::Withdrawn, $request->refresh()->status);
        $this->assertSame(AgreementStatus::Active, $agreement->refresh()->status);
    }

    public function test_an_addendum_denial_cancels_only_the_extension_at_once(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $addendum = Addendum::factory()->create(['agreement_id' => $agreement->id, 'reference' => $agreement->reference.'-A1']);

        $this->actingAs($student)
            ->post(route('agreements.addenda.denials.store', $this->asParty($student, $agreement, ['addendum' => $addendum])), ['reason' => 'Too much on my plate this term.'])
            ->assertSessionHasNoErrors();

        $request = ContractRequest::query()->sole();
        $this->assertSame(ContractRequestStatus::Denied, $request->status);
        $this->assertSame(AddendumStatus::Cancelled, $addendum->refresh()->status);
        $this->assertSame(AgreementStatus::Active, $agreement->refresh()->status);
        $this->assertTrue($agreement->hasDeniedExtension());

        /* The client cannot deny the denial back. */
        $this->actingAs($client)
            ->post(route('agreements.requests.decline', $this->asParty($client, $agreement, ['contractRequest' => $request])))
            ->assertForbidden();
        $this->actingAs($client)
            ->post(route('agreements.addenda.denials.store', $this->asParty($client, $agreement, ['addendum' => $addendum])), ['reason' => 'I deny your denial.'])
            ->assertForbidden();

        $this->actingAs($client)
            ->get(route('agreements.addenda.show', $this->asParty($client, $agreement, ['addendum' => $addendum])))
            ->assertInertia(fn (Assert $page) => $page
                ->where('addendum.denial.status', 'denied')
                ->where('addendum.denial.canDecide', false));
    }

    public function test_the_denial_notification_reads_as_a_line_with_a_link(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $addendum = Addendum::factory()->create(['agreement_id' => $agreement->id, 'reference' => $agreement->reference.'-A1']);

        $this->actingAs($student)
            ->post(route('agreements.addenda.denials.store', $this->asParty($student, $agreement, ['addendum' => $addendum])), ['reason' => 'Too much on my plate this term.']);

        $notification = new ContractRequestUpdated(ContractRequest::query()->sole(), 'denied');

        /* Notifications are faked here, so the stored row is written as the database channel would. */
        $row = $client->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ContractRequestUpdated::class,
            'data' => $notification->toArray($client),
        ]);

        $line = app(PresentNotification::class)->handle($row, $client->currentTeam);

        $this->assertStringContainsString('The student denied the project extension', $line['title']);
        $this->assertStringContainsString('Too much on my plate this term.', $line['body']);
        $this->assertStringContainsString('/addenda/'.$addendum->id, (string) $line['url']);
    }

    public function test_a_signed_agreement_cannot_be_denied(): void
    {
        ['student' => $student, 'agreement' => $agreement] = $this->collaboration();

        $this->actingAs($student)
            ->post(route('agreements.denials.store', $this->asParty($student, $agreement)), ['reason' => 'Too late for this one.'])
            ->assertForbidden();
    }

    public function test_a_student_termination_completes_the_project_with_the_clients_rating(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();
        $openExtension = Addendum::factory()->create(['agreement_id' => $agreement->id, 'reference' => $agreement->reference.'-A1']);

        $this->actingAs($student)
            ->post(route('agreements.terminations.store', $this->asParty($student, $agreement)), ['reason' => 'I am transferring schools.'])
            ->assertSessionHasNoErrors();

        $request = ContractRequest::query()->sole();
        $this->assertNull($request->rating);

        /* The client agreeing rates the work: completing is rating. */
        $this->actingAs($client)
            ->post(route('agreements.requests.accept', $this->asParty($client, $agreement, ['contractRequest' => $request])))
            ->assertSessionHasErrors('rating');

        $this->actingAs($client)
            ->post(route('agreements.requests.accept', $this->asParty($client, $agreement, ['contractRequest' => $request])), ['rating' => 4, 'feedback' => 'Good work so far.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(AgreementStatus::Completed, $agreement->refresh()->status);
        $this->assertSame(ProjectStatus::Completed, $agreement->project->refresh()->status);
        $this->assertSame(AddendumStatus::Cancelled, $openExtension->refresh()->status);
        $this->assertSame(4, ProjectRating::query()->where('student_id', $student->id)->value('rating'));
        $this->assertFalse($student->fresh()->isLockedToProject());
    }

    public function test_a_client_termination_carries_the_rating_and_completes_once_the_student_agrees(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();

        $this->actingAs($client)
            ->post(route('agreements.terminations.store', $this->asParty($client, $agreement)), ['reason' => 'Our plans changed.'])
            ->assertSessionHasErrors('rating');

        $this->actingAs($client)
            ->post(route('agreements.terminations.store', $this->asParty($client, $agreement)), ['reason' => 'Our plans changed.', 'rating' => 5])
            ->assertSessionHasNoErrors();

        $this->assertSame(AgreementStatus::Active, $agreement->refresh()->status);

        $request = ContractRequest::query()->sole();

        $this->actingAs($student)
            ->post(route('agreements.requests.accept', $this->asParty($student, $agreement, ['contractRequest' => $request])))
            ->assertSessionHasNoErrors();

        $this->assertSame(AgreementStatus::Completed, $agreement->refresh()->status);
        $this->assertSame(5, ProjectRating::query()->where('student_id', $student->id)->value('rating'));
    }

    public function test_project_management_shows_the_waiting_termination_to_both_sides(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();

        $this->actingAs($client)
            ->get(route('project-management', ['current_team' => $client->currentTeam, 'agreement' => $agreement->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('agreement.canRequestTermination', true)
                ->where('agreement.termination', null));

        $this->actingAs($student)
            ->post(route('agreements.terminations.store', $this->asParty($student, $agreement)), ['reason' => 'I am transferring schools.']);

        $this->actingAs($client)
            ->get(route('project-management', ['current_team' => $client->currentTeam, 'agreement' => $agreement->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('agreement.canRequestTermination', false)
                ->where('agreement.termination.reason', 'I am transferring schools.')
                ->where('agreement.termination.canDecide', true)
                ->where('agreement.termination.needsRating', true));

        $this->actingAs($student)
            ->get(route('project-management', ['current_team' => $student->currentTeam, 'agreement' => $agreement->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('agreement.termination.canWithdraw', true)
                ->where('agreement.termination.canDecide', false));
    }

    public function test_the_notification_reads_as_a_line_with_a_link(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement] = $this->collaboration();

        $this->actingAs($student)
            ->post(route('agreements.terminations.store', $this->asParty($student, $agreement)), ['reason' => 'I am transferring schools.']);

        $request = ContractRequest::query()->sole();
        $notification = new ContractRequestUpdated($request, 'requested');

        /* Notifications are faked here, so the stored row is written as the database channel would. */
        $row = $client->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ContractRequestUpdated::class,
            'data' => $notification->toArray($client),
        ]);

        $line = app(PresentNotification::class)->handle($row, $client->currentTeam);

        $this->assertStringContainsString('ending the collaboration', $line['title']);
        $this->assertStringContainsString('I am transferring schools.', $line['body']);
        $this->assertStringContainsString('project-management', (string) $line['url']);
    }

    public function test_only_the_two_parties_ask(): void
    {
        ['agreement' => $agreement] = $this->collaboration();
        $outsider = User::factory()->student()->create();

        $this->assertFalse(Gate::forUser($outsider)->allows('terminate', $agreement));
        $this->assertFalse(Gate::forUser($outsider)->allows('deny', Agreement::factory()->create()));
    }
}
