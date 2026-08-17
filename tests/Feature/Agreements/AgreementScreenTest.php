<?php

namespace Tests\Feature\Agreements;

use App\Actions\Agreements\DraftAgreement;
use App\Enums\AgreementStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Models\Agreement;
use App\Models\Application;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The screens both parties read the contract through.
 */
class AgreementScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_parties_can_read_the_full_contract(): void
    {
        [$owner, $student, $agreement] = $this->agreement();

        foreach ([$owner, $student] as $reader) {
            $this->actingAs($reader)
                ->get(route('agreements.contract', [
                    'current_team' => $reader->currentTeam,
                    'agreement' => $agreement,
                ]))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('agreements/contract')
                    ->where('agreement.reference', $agreement->reference));
        }
    }

    public function test_a_stranger_cannot_read_the_full_contract(): void
    {
        [, , $agreement] = $this->agreement();

        $stranger = User::factory()->student()->approved()->create();

        $this->actingAs($stranger)
            ->get(route('agreements.contract', [
                'current_team' => $stranger->currentTeam,
                'agreement' => $agreement,
            ]))
            ->assertForbidden();
    }

    public function test_the_index_goes_straight_to_the_only_standing_agreement(): void
    {
        [$owner, , $agreement] = $this->agreement();

        $this->actingAs($owner)
            ->get(route('agreements.index', ['current_team' => $owner->currentTeam]))
            ->assertRedirect(route('agreements.show', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]));
    }

    public function test_the_index_lists_agreements_when_none_is_standing(): void
    {
        [$owner, , $agreement] = $this->agreement();

        $agreement->update(['status' => AgreementStatus::Superseded]);

        $this->actingAs($owner)
            ->get(route('agreements.index', ['current_team' => $owner->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('agreements/index')
                ->has('agreements', 1)
                ->where('agreements.0.reference', $agreement->reference));
    }

    public function test_the_index_is_empty_for_somebody_with_no_contracts(): void
    {
        $student = User::factory()->student()->approved()->create();

        $this->actingAs($student)
            ->get(route('agreements.index', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('agreements/index')
                ->has('agreements', 0));
    }

    public function test_the_client_can_price_the_terms_from_the_agreement_screen(): void
    {
        [$owner, $student, $agreement] = $this->agreement();

        $this->actingAs($owner)
            ->patch(route('agreements.update', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]), [
                'scope_summary' => 'Full-stack inventory system with predictive reorder analytics.',
                'deliverables' => ['Stock and supplier modules', 'Forecast dashboard'],
                'intellectual_property_terms' => 'Transfers on final payment.',
                'confidentiality_terms' => 'Client data stays confidential.',
                'academic_terms' => 'The panel may see the architecture.',
                'starts_on' => now()->toDateString(),
                'ends_on' => now()->addMonths(2)->toDateString(),
                'milestones' => [
                    [
                        'title' => 'Design',
                        'amount' => 8000,
                        'starts_on' => now()->toDateString(),
                        'ends_on' => now()->addWeeks(3)->toDateString(),
                    ],
                    [
                        'title' => 'Build',
                        'amount' => 14000,
                        'starts_on' => now()->addWeeks(3)->toDateString(),
                        'ends_on' => now()->addWeeks(8)->toDateString(),
                    ],
                ],
            ])
            ->assertRedirect();

        $agreement->refresh();

        $this->assertSame(22000, $agreement->total_amount);
        $this->assertCount(2, $agreement->milestones);

        // The student reads the figures the client just wrote, not a copy.
        $this->actingAs($student)
            ->get(route('agreements.show', [
                'current_team' => $student->currentTeam,
                'agreement' => $agreement,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement.totalAmount', 22000)
                ->where('agreement.viewer.canEdit', false)
                ->where('agreement.viewer.party', 'student'));
    }

    public function test_the_client_can_reorder_the_milestones(): void
    {
        [$owner, , $agreement] = $this->agreement();

        $original = $agreement->milestones;

        // The same three rows, first and last swapped. Position is unique per
        // agreement, so this is the case that collides if the new order is
        // written straight over the old one.
        $this->actingAs($owner)
            ->patch(route('agreements.update', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]), [
                ...$this->terms(),
                'milestones' => [
                    $this->milestone($original[2]->id, $original[2]->title),
                    $this->milestone($original[1]->id, $original[1]->title),
                    $this->milestone($original[0]->id, $original[0]->title),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $reordered = $agreement->refresh()->milestones;

        $this->assertSame([1, 2, 3], $reordered->pluck('position')->all());
        $this->assertSame(
            [$original[2]->id, $original[1]->id, $original[0]->id],
            $reordered->pluck('id')->all(),
        );
    }

    public function test_dropping_a_milestone_removes_it(): void
    {
        [$owner, , $agreement] = $this->agreement();

        $kept = $agreement->milestones->first();

        $this->actingAs($owner)
            ->patch(route('agreements.update', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]), [
                ...$this->terms(),
                'milestones' => [$this->milestone($kept->id, $kept->title)],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame([$kept->id], $agreement->refresh()->milestones->pluck('id')->all());
    }

    public function test_the_ledger_is_advertised_as_off_by_default(): void
    {
        [$owner, , $agreement] = $this->agreement();

        $this->actingAs($owner)
            ->get(route('agreements.show', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('billingEnabled', false));
    }

    /**
     * The non-milestone half of a terms submission.
     *
     * @return array<string, mixed>
     */
    private function terms(): array
    {
        return [
            'scope_summary' => 'Full-stack inventory system.',
            'intellectual_property_terms' => 'Transfers on final payment.',
            'confidentiality_terms' => 'Client data stays confidential.',
            'academic_terms' => 'The panel may see the architecture.',
        ];
    }

    /**
     * One priced milestone row as the screen submits it.
     *
     * @return array<string, mixed>
     */
    private function milestone(int $id, string $title): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'amount' => 5000,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addWeeks(3)->toDateString(),
        ];
    }

    /**
     * A drafted agreement and the two people who are party to it.
     *
     * @return array{0: User, 1: User, 2: Agreement}
     */
    private function agreement(): array
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
}
