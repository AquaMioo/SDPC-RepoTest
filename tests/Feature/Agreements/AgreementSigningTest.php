<?php

namespace Tests\Feature\Agreements;

use App\Actions\Agreements\DraftAgreement;
use App\Enums\AgreementParty;
use App\Enums\AgreementStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Models\Agreement;
use App\Models\Application;
use App\Models\Project;
use App\Models\User;
use App\Notifications\Agreements\AgreementSigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Signing is what starts the work, and it takes both sides.
 */
class AgreementSigningTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_parties_can_read_the_agreement(): void
    {
        [$owner, $student, $agreement] = $this->pricedAgreement();

        $this->actingAs($owner)
            ->get(route('agreements.show', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]))
            ->assertOk();

        $this->actingAs($student)
            ->get(route('agreements.show', [
                'current_team' => $student->currentTeam,
                'agreement' => $agreement,
            ]))
            ->assertOk();
    }

    public function test_a_stranger_cannot_read_the_agreement(): void
    {
        [, , $agreement] = $this->pricedAgreement();

        $stranger = User::factory()->student()->approved()->create();

        $this->actingAs($stranger)
            ->get(route('agreements.show', [
                'current_team' => $stranger->currentTeam,
                'agreement' => $agreement,
            ]))
            ->assertForbidden();
    }

    public function test_one_signature_leaves_the_project_open(): void
    {
        [$owner, , $agreement] = $this->pricedAgreement();

        $this->actingAs($owner)
            ->post($this->signUrl($owner, $agreement), $this->signature('Samuel Clemens'))
            ->assertRedirect();

        $agreement->refresh();

        $this->assertSame(AgreementStatus::AwaitingSignatures, $agreement->status);
        $this->assertTrue($agreement->isSignedBy(AgreementParty::Client));
        $this->assertSame(ProjectStatus::Open, $agreement->project->refresh()->status);
    }

    public function test_the_second_signature_starts_the_project(): void
    {
        [$owner, $student, $agreement] = $this->pricedAgreement();

        $this->actingAs($owner)
            ->post($this->signUrl($owner, $agreement), $this->signature('Samuel Clemens'));

        $this->actingAs($student)
            ->post($this->signUrl($student, $agreement), $this->signature('Jeremie Caasi'));

        $agreement->refresh();

        $this->assertSame(AgreementStatus::Active, $agreement->status);
        $this->assertNotNull($agreement->activated_at);
        $this->assertSame(ProjectStatus::InProgress, $agreement->project->refresh()->status);
    }

    public function test_the_signature_records_who_signed_and_when(): void
    {
        [, $student, $agreement] = $this->pricedAgreement();

        $this->actingAs($student)
            ->post($this->signUrl($student, $agreement), $this->signature('Jeremie Caasi'));

        $signature = $agreement->signatures()->firstOrFail();

        $this->assertSame($student->id, $signature->user_id);
        $this->assertSame('Jeremie Caasi', $signature->signed_name);
        $this->assertSame(AgreementParty::Student, $signature->party);
        $this->assertNotNull($signature->signed_at);
    }

    public function test_nobody_signs_twice(): void
    {
        [, $student, $agreement] = $this->pricedAgreement();

        $this->actingAs($student)
            ->post($this->signUrl($student, $agreement), $this->signature('Jeremie Caasi'));

        $this->actingAs($student)
            ->post($this->signUrl($student, $agreement), $this->signature('Jeremie Caasi'))
            ->assertForbidden();

        $this->assertSame(1, $agreement->signatures()->count());
    }

    public function test_a_partial_acknowledgement_is_refused(): void
    {
        [, $student, $agreement] = $this->pricedAgreement();

        $this->actingAs($student)
            ->post($this->signUrl($student, $agreement), [
                'signed_name' => 'Jeremie Caasi',
                'acknowledgements' => ['intellectual_property'],
            ])
            ->assertSessionHasErrors('acknowledgements');

        $this->assertSame(0, $agreement->signatures()->count());
    }

    public function test_an_unpriced_agreement_cannot_be_signed(): void
    {
        [, $student, $agreement] = $this->draftAgreement();

        // Every milestone still sits at zero with no end date, which is a blank
        // in the contract. A signature against a blank is worth nothing.
        $this->actingAs($student)
            ->post($this->signUrl($student, $agreement), $this->signature('Jeremie Caasi'))
            ->assertSessionHasErrors('signed_name');

        $this->assertSame(0, $agreement->signatures()->count());
    }

    public function test_the_other_side_is_told_a_signature_is_waiting(): void
    {
        Notification::fake();

        [$owner, $student, $agreement] = $this->pricedAgreement();

        $this->actingAs($owner)
            ->post($this->signUrl($owner, $agreement), $this->signature('Samuel Clemens'));

        Notification::assertSentTo($student, AgreementSigned::class);
    }

    public function test_a_student_cannot_rewrite_the_terms(): void
    {
        [, $student, $agreement] = $this->pricedAgreement();

        $this->actingAs($student)
            ->patch(route('agreements.update', [
                'current_team' => $student->currentTeam,
                'agreement' => $agreement,
            ]), [
                'scope_summary' => 'Rewritten by the student',
                'intellectual_property_terms' => 'x',
                'confidentiality_terms' => 'x',
                'academic_terms' => 'x',
                'milestones' => [['title' => 'One', 'amount' => 1]],
            ])
            ->assertForbidden();
    }

    public function test_the_client_cannot_rewrite_terms_after_anybody_signs(): void
    {
        [$owner, $student, $agreement] = $this->pricedAgreement();

        $this->actingAs($student)
            ->post($this->signUrl($student, $agreement), $this->signature('Jeremie Caasi'));

        $this->actingAs($owner)
            ->patch(route('agreements.update', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]), [
                'scope_summary' => 'Moved after signing',
                'intellectual_property_terms' => 'x',
                'confidentiality_terms' => 'x',
                'academic_terms' => 'x',
                'milestones' => [['title' => 'One', 'amount' => 1]],
            ])
            ->assertForbidden();
    }

    /**
     * The four acknowledgement keys plus a typed name.
     *
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
     * Build the signing URL for whichever party is acting.
     */
    private function signUrl(User $user, Agreement $agreement): string
    {
        return route('agreements.signatures.store', [
            'current_team' => $user->currentTeam,
            'agreement' => $agreement,
        ]);
    }

    /**
     * A drafted agreement with nothing priced yet.
     *
     * @return array{0: User, 1: User, 2: Agreement}
     */
    private function draftAgreement(): array
    {
        $owner = User::factory()->verifiedBusiness()->create();
        $student = User::factory()->student()->approved()->create();

        $project = Project::factory()->create([
            'team_id' => $owner->current_team_id,
            'status' => ProjectStatus::Open,
        ]);

        $application = Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Accepted,
        ]);

        return [$owner, $student, app(DraftAgreement::class)->handle($application)];
    }

    /**
     * A drafted agreement the client has finished pricing.
     *
     * @return array{0: User, 1: User, 2: Agreement}
     */
    private function pricedAgreement(): array
    {
        [$owner, $student, $agreement] = $this->draftAgreement();

        $agreement->milestones->each(fn ($milestone, $index) => $milestone->update([
            'amount' => 8000,
            'starts_on' => now()->addWeeks($index * 3)->toDateString(),
            'ends_on' => now()->addWeeks($index * 3 + 2)->toDateString(),
        ]));

        $agreement->refresh()->syncTotalAmount();

        return [$owner, $student, $agreement->refresh()];
    }
}
