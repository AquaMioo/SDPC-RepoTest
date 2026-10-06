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
use Illuminate\Support\Carbon;
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

        /* The phases are Objective & Scope and Turnover. */
        [$work, $turnover] = $agreement->milestones;

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
                'ends_on' => now()->addMonths(3)->toDateString(),
                'milestones' => [
                    [
                        'id' => $work->id,
                        'amount' => 8000,
                        /* Sent, but Objective & Scope keeps no dates. */
                        'starts_on' => now()->toDateString(),
                        'ends_on' => now()->addWeeks(3)->toDateString(),
                    ],
                    [
                        'id' => $turnover->id,
                        'amount' => 14000,
                        'starts_on' => now()->addWeeks(8)->addDay()->toDateString(),
                        'ends_on' => now()->addWeeks(8)->addDay()->addMonth()->toDateString(),
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $agreement->refresh();

        $this->assertSame(22000, $agreement->total_amount);
        $this->assertCount(2, $agreement->milestones);
        $this->assertSame(['Objective & Scope', 'Turnover'], $agreement->milestones->pluck('title')->all());
        /* The timeline is Turnover's dates only (owner, 2026-10-07). */
        $this->assertNull($agreement->milestones[0]->starts_on);
        $this->assertNull($agreement->milestones[0]->ends_on);
        $this->assertNotNull($agreement->milestones[1]->ends_on);

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

    /**
     * The phases are Objective & Scope and Turnover. The client's timeline
     * form sets Turnover's dates; it cannot put them in another order or
     * rename them.
     */
    public function test_the_timeline_form_cannot_reorder_or_rename_the_phases(): void
    {
        [$owner, , $agreement] = $this->agreement();

        $original = $agreement->milestones;

        $this->actingAs($owner)
            ->patch(route('agreements.update', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]), [
                ...$this->terms(),
                'milestones' => [
                    $this->milestone($original[1]->id, 'Renamed turnover'),
                    $this->milestone($original[0]->id, 'Renamed work'),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $after = $agreement->refresh()->milestones;

        $this->assertSame([1, 2], $after->pluck('position')->all());
        $this->assertSame($original->pluck('id')->all(), $after->pluck('id')->all());
        $this->assertSame(['Objective & Scope', 'Turnover'], $after->pluck('title')->all());
        /* Turnover's dates did land. */
        $this->assertSame(now()->addWeeks(5)->toDateString(), $after[1]->ends_on->toDateString());
    }

    /**
     * Leaving a phase out of the timeline form does not delete it.
     */
    public function test_the_timeline_form_cannot_drop_a_phase(): void
    {
        [$owner, , $agreement] = $this->agreement();

        [$work, $turnover] = $agreement->milestones;

        $this->actingAs($owner)
            ->patch(route('agreements.update', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]), [
                ...$this->terms(),
                'milestones' => [$this->milestone($turnover->id, $turnover->title)],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame([$work->id, $turnover->id], $agreement->refresh()->milestones->pluck('id')->all());
    }

    public function test_turnover_has_to_run_at_least_a_month(): void
    {
        [$owner, , $agreement] = $this->agreement();
        $turnover = $agreement->milestones->last();

        $this->actingAs($owner)
            ->patch(route('agreements.update', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]), [
                ...$this->terms(),
                'milestones' => [[
                    ...$this->milestone($turnover->id, $turnover->title),
                    'starts_on' => now()->toDateString(),
                    'ends_on' => now()->addMonthNoOverflow()->subDay()->toDateString(),
                ]],
            ])
            ->assertSessionHasErrors([
                'milestones.0.ends_on' => 'Turnover has to run at least one month. End it on or after '.now()->addMonthNoOverflow()->format('j M Y').'.',
            ]);

        $this->actingAs($owner)
            ->patch(route('agreements.update', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]), [
                ...$this->terms(),
                'milestones' => [[
                    ...$this->milestone($turnover->id, $turnover->title),
                    'starts_on' => now()->toDateString(),
                    'ends_on' => now()->addMonthNoOverflow()->toDateString(),
                ]],
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_a_date_with_a_year_past_four_digits_is_refused(): void
    {
        [$owner, , $agreement] = $this->agreement();

        $kept = $agreement->milestones->first();

        /*
         * A browser date box lets the year run to six digits. The plain
         * `date` rule let "202666-01-01" through to a DATE column; the answer
         * must be a message on the field, not a server error (2026-09-19).
         */
        $this->actingAs($owner)
            ->patch(route('agreements.update', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]), [
                ...$this->terms(),
                'starts_on' => '202666-01-01',
                'milestones' => [[
                    ...$this->milestone($kept->id, $kept->title),
                    'ends_on' => '20266-03-01',
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['starts_on', 'milestones.0.ends_on']);
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
    public function test_work_cannot_start_in_the_past(): void
    {
        [$owner, , $agreement] = $this->agreement();
        $first = $agreement->milestones->first();

        $this->actingAs($owner)
            ->patch(route('agreements.update', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]), [
                ...$this->terms(),
                'milestones' => [[
                    ...$this->milestone($first->id, $first->title),
                    'starts_on' => now()->subDay()->toDateString(),
                ]],
            ])
            ->assertSessionHasErrors([
                'milestones.0.starts_on' => 'Dates cannot be in the past. Pick today or a later date.',
            ]);

        /* An end date in the past is refused too, even with no start date. */
        $this->actingAs($owner)
            ->patch(route('agreements.update', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]), [
                ...$this->terms(),
                'milestones' => [[
                    ...$this->milestone($first->id, $first->title),
                    'starts_on' => null,
                    'ends_on' => now()->subDay()->toDateString(),
                ]],
            ])
            ->assertSessionHasErrors([
                'milestones.0.ends_on' => 'Dates cannot be in the past. Pick today or a later date.',
            ]);
    }

    /**
     * Today counts in Singapore Time: at 7am SGT it is already the next day
     * in the app, even though it is still the evening before in UTC.
     */
    public function test_today_is_counted_in_singapore_time(): void
    {
        $this->travelTo(Carbon::parse('2026-10-04 23:00:00', 'UTC'));

        [$owner, , $agreement] = $this->agreement();
        $turnover = $agreement->milestones->last();

        $this->assertSame('2026-10-05', today()->toDateString());

        $this->actingAs($owner)
            ->patch(route('agreements.update', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]), [
                ...$this->terms(),
                'milestones' => [[
                    ...$this->milestone($turnover->id, $turnover->title),
                    'starts_on' => '2026-10-04',
                ]],
            ])
            ->assertSessionHasErrors(['milestones.0.starts_on' => 'Dates cannot be in the past. Pick today or a later date.']);
    }

    public function test_the_timeline_cannot_run_longer_than_a_year(): void
    {
        [$owner, , $agreement] = $this->agreement();
        $first = $agreement->milestones->first();

        $this->actingAs($owner)
            ->patch(route('agreements.update', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]), [
                ...$this->terms(),
                'milestones' => [[
                    ...$this->milestone($first->id, $first->title),
                    'starts_on' => now()->toDateString(),
                    'ends_on' => now()->addYear()->addDay()->toDateString(),
                ]],
            ])
            ->assertSessionHasErrors([
                'milestones.0.ends_on' => 'Dates cannot be more than one year from today. Pick '.now()->addYear()->format('j M Y').' or earlier.',
            ]);

        /* A start more than a year out is refused the same way. */
        $this->actingAs($owner)
            ->patch(route('agreements.update', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]), [
                ...$this->terms(),
                'milestones' => [[
                    ...$this->milestone($first->id, $first->title),
                    'starts_on' => now()->addYear()->addDay()->toDateString(),
                    'ends_on' => now()->addYear()->addMonths(2)->toDateString(),
                ]],
            ])
            ->assertSessionHasErrors(['milestones.0.starts_on', 'milestones.0.ends_on']);

        /* Exactly a year is still allowed. */
        $this->actingAs($owner)
            ->patch(route('agreements.update', [
                'current_team' => $owner->currentTeam,
                'agreement' => $agreement,
            ]), [
                ...$this->terms(),
                'milestones' => [[
                    ...$this->milestone($first->id, $first->title),
                    'starts_on' => now()->toDateString(),
                    'ends_on' => now()->addYear()->toDateString(),
                ]],
            ])
            ->assertSessionHasNoErrors();
    }

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
            /* Long enough for Turnover, which runs at least a month. */
            'ends_on' => now()->addWeeks(5)->toDateString(),
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
