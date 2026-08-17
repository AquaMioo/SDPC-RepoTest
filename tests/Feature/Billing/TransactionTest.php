<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\RecordTransaction;
use App\Enums\TransactionStatus;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The ledger is built, tested, and ships switched off.
 *
 * Every test that needs the screen forces the flag on. The two that assert the
 * shipped default deliberately do not.
 */
class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_ledger_is_sealed_on_a_normal_boot(): void
    {
        $this->assertFalse(config('billing.enabled'));

        $student = User::factory()->student()->approved()->create();

        $this->actingAs($student)
            ->get(route('transactions.index', ['current_team' => $student->currentTeam]))
            ->assertNotFound();
    }

    public function test_nothing_is_written_while_it_is_switched_off(): void
    {
        $milestone = AgreementMilestone::factory()->approved()->create();

        $this->assertNull(app(RecordTransaction::class)->forMilestone($milestone));
        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_a_student_reads_the_ledger_as_the_payee(): void
    {
        config(['billing.enabled' => true]);

        $student = User::factory()->student()->approved()->create();

        Transaction::factory()->settled()->create([
            'payee_user_id' => $student->id,
            'amount' => 8000,
            'description' => 'Parrot Inventory System · Design',
        ]);

        /* Somebody else's money must not appear in their ledger. */
        Transaction::factory()->create(['amount' => 99000]);

        $this->actingAs($student)
            ->get(route('transactions.index', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('billing/transactions')
                ->has('transactions', 1)
                ->where('transactions.0.description', 'Parrot Inventory System · Design')
                ->where('totals.settled', 8000)
                ->where('isStudent', true));
    }

    public function test_a_client_reads_the_ledger_as_the_payer(): void
    {
        config(['billing.enabled' => true]);

        $owner = User::factory()->verifiedBusiness()->create();

        Transaction::factory()->create([
            'payer_team_id' => $owner->current_team_id,
            'amount' => 14000,
        ]);

        Transaction::factory()->create(['amount' => 99000]);

        $this->actingAs($owner)
            ->get(route('transactions.index', ['current_team' => $owner->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('transactions', 1)
                // Pending is a promise, not money — it sits outstanding.
                ->where('totals.settled', 0)
                ->where('totals.outstanding', 14000)
                ->where('isStudent', false));
    }

    public function test_approving_a_milestone_bills_it_once(): void
    {
        config(['billing.enabled' => true]);

        $agreement = Agreement::factory()->active()->create();

        $milestone = AgreementMilestone::factory()->approved()->create([
            'agreement_id' => $agreement->id,
            'amount' => 8000,
        ]);

        $record = app(RecordTransaction::class);

        $first = $record->forMilestone($milestone);
        $second = $record->forMilestone($milestone->refresh());

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertSame(1, Transaction::query()->count());
        $this->assertSame(8000, $first->amount);
        // The platform does not move money and will not claim that it has.
        $this->assertSame(TransactionStatus::Pending, $first->status);
    }

    public function test_the_wallet_filter_narrows_the_ledger(): void
    {
        config(['billing.enabled' => true]);

        $student = User::factory()->student()->approved()->create();

        $gcash = Transaction::factory()->settled()->create([
            'payee_user_id' => $student->id,
        ]);

        Transaction::factory()->create(['payee_user_id' => $student->id]);

        $this->actingAs($student)
            ->get(route('transactions.index', [
                'current_team' => $student->currentTeam,
                'wallet' => 'gcash',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('transactions', 1)
                ->where('transactions.0.id', $gcash->id));
    }
}
