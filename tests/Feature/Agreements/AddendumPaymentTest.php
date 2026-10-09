<?php

namespace Tests\Feature\Agreements;

use App\Enums\AddendumPaymentStatus;
use App\Enums\AddendumStatus;
use App\Enums\TaskStatus;
use App\Models\Addendum;
use App\Models\AddendumService;
use App\Models\Agreement;
use App\Models\AgreementTask;
use App\Models\User;
use App\Notifications\Agreements\AddendumPaymentCleared;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Agreements\Concerns\StartsCollaboration;
use Tests\TestCase;

/**
 * The addendum's two milestones: the down payment once both have signed, the
 * final balance once the extended work is handed in, both through PayMongo's
 * GCash checkout (or SDPC's simulated one while no key is set), and the
 * extension's files locked from the client until the second clears.
 */
class AddendumPaymentTest extends TestCase
{
    use RefreshDatabase, StartsCollaboration;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake('public');

        config([
            'services.paymongo.secret_key' => null,
            'services.paymongo.webhook_secret' => null,
        ]);
    }

    public function test_the_down_payment_goes_through_the_simulated_checkout_and_starts_the_work(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement, 'addendum' => $addendum] = $this->executedAddendum();
        $downPayment = $addendum->payment(1);

        $this->actingAs($client)
            ->post(route('agreements.addenda.payments.checkout', $this->paymentArgs($client, $agreement, $addendum, $downPayment)))
            ->assertRedirect(route('payments.simulated.show', $downPayment));

        $this->actingAs($client)
            ->get(route('payments.simulated.show', $downPayment))
            ->assertInertia(fn (Assert $page) => $page
                ->component('agreements/simulated-checkout')
                ->where('payment.amountLabel', '₱3,000.00'));

        $this->actingAs($client)
            ->post(route('payments.simulated.store', $downPayment))
            ->assertRedirect(route('agreements.addenda.show', $this->asParty($client, $agreement, ['addendum' => $addendum])));

        $downPayment->refresh();
        $this->assertSame(AddendumPaymentStatus::Paid, $downPayment->status);
        $this->assertStringStartsWith('pay_sim_', $downPayment->provider_payment_id);
        $this->assertSame('simulated', $downPayment->gateway);
        $this->assertSame($client->id, $downPayment->paid_by);

        /* Section II's services are now Objective & Scope tasks, marked as the extension's. */
        $tasks = AgreementTask::query()->where('addendum_id', $addendum->id)->get();
        $this->assertSame(['Post-Deployment Maintenance', 'Feature Enhancements'], $tasks->pluck('title')->all());
        $this->assertTrue($tasks->every(fn (AgreementTask $task): bool => $task->agreement_milestone_id === $agreement->milestones->first()->id));

        Notification::assertSentTo($student, AddendumPaymentCleared::class);
    }

    public function test_the_final_balance_waits_for_the_work_and_then_completes_the_addendum(): void
    {
        ['client' => $client, 'agreement' => $agreement, 'addendum' => $addendum] = $this->executedAddendum();

        $this->payInSimulation($client, $addendum, 1);

        $final = $addendum->refresh()->payment(2);

        $this->actingAs($client)
            ->post(route('agreements.addenda.payments.checkout', $this->paymentArgs($client, $agreement, $addendum, $final)))
            ->assertSessionHasErrors(['payment' => 'The final balance is due once the down payment has cleared and the student has handed in every extended task.']);

        AgreementTask::query()->where('addendum_id', $addendum->id)->update(['status' => TaskStatus::Submitted]);

        $this->payInSimulation($client, $addendum->refresh(), 2);

        $addendum->refresh();
        $this->assertSame(AddendumStatus::Completed, $addendum->status);
        $this->assertNotNull($addendum->completed_at);
        $this->assertSame(AddendumPaymentStatus::Paid, $addendum->payment(2)->status);
    }

    public function test_only_the_client_pays(): void
    {
        ['student' => $student, 'agreement' => $agreement, 'addendum' => $addendum] = $this->executedAddendum();
        $downPayment = $addendum->payment(1);

        $this->actingAs($student)
            ->post(route('agreements.addenda.payments.checkout', $this->paymentArgs($student, $agreement, $addendum, $downPayment)))
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('payments.simulated.store', $downPayment))
            ->assertForbidden();

        $this->assertSame(AddendumPaymentStatus::Pending, $downPayment->refresh()->status);
    }

    public function test_paymongo_opens_a_gcash_checkout_and_the_return_settles_it(): void
    {
        config(['services.paymongo.secret_key' => 'sk_test_example']);

        Http::fake([
            'api.paymongo.com/v1/checkout_sessions/*' => Http::response(['data' => [
                'id' => 'cs_test_123',
                'attributes' => [
                    'status' => 'active',
                    'payments' => [['id' => 'pay_test_456', 'attributes' => ['status' => 'paid']]],
                ],
            ]]),
            'api.paymongo.com/v1/checkout_sessions' => Http::response(['data' => [
                'id' => 'cs_test_123',
                'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_test_123'],
            ]]),
        ]);

        ['client' => $client, 'agreement' => $agreement, 'addendum' => $addendum] = $this->executedAddendum();
        $downPayment = $addendum->payment(1);
        $args = $this->paymentArgs($client, $agreement, $addendum, $downPayment);

        $this->actingAs($client)
            ->post(route('agreements.addenda.payments.checkout', $args))
            ->assertRedirect('https://checkout.paymongo.com/cs_test_123');

        Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_example:'))
            && $request['data']['attributes']['payment_method_types'] === ['gcash']
            && $request['data']['attributes']['line_items'][0]['amount'] === 300000
            && $request['data']['attributes']['reference_number'] === $downPayment->invoice_number);

        $this->assertSame('cs_test_123', $downPayment->refresh()->checkout_session_id);
        $this->assertSame('paymongo', $downPayment->gateway);

        $this->actingAs($client)
            ->get(route('agreements.addenda.payments.paid', $args))
            ->assertRedirect(route('agreements.addenda.show', $this->asParty($client, $agreement, ['addendum' => $addendum])));

        $downPayment->refresh();
        $this->assertSame(AddendumPaymentStatus::Paid, $downPayment->status);
        $this->assertSame('pay_test_456', $downPayment->provider_payment_id);

        /* The simulated checkout is gone once a key is set. */
        $this->actingAs($client)->get(route('payments.simulated.show', $addendum->payment(2)))->assertNotFound();
    }

    public function test_a_signed_webhook_settles_the_milestone_once(): void
    {
        config(['services.paymongo.webhook_secret' => 'whsk_test_secret']);

        ['student' => $student, 'addendum' => $addendum] = $this->executedAddendum();
        $downPayment = $addendum->payment(1);
        $downPayment->update(['checkout_session_id' => 'cs_live_789', 'gateway' => 'paymongo']);

        $payload = json_encode(['data' => ['attributes' => [
            'type' => 'checkout_session.payment.paid',
            'data' => [
                'id' => 'cs_live_789',
                'attributes' => ['payments' => [['id' => 'pay_live_999', 'attributes' => ['status' => 'paid']]]],
            ],
        ]]]);

        $this->webhook($payload, 'not-the-secret')->assertStatus(400);
        $this->assertSame(AddendumPaymentStatus::Pending, $downPayment->refresh()->status);

        $this->webhook($payload, 'whsk_test_secret')->assertOk();
        $this->webhook($payload, 'whsk_test_secret')->assertOk();

        $downPayment->refresh();
        $this->assertSame(AddendumPaymentStatus::Paid, $downPayment->status);
        $this->assertSame('pay_live_999', $downPayment->provider_payment_id);
        $this->assertSame(2, AgreementTask::query()->where('addendum_id', $addendum->id)->count());

        Notification::assertSentToTimes($student, AddendumPaymentCleared::class, 1);
    }

    public function test_the_webhook_is_closed_without_a_secret(): void
    {
        $this->postJson(route('webhooks.paymongo'), [])->assertNotFound();
    }

    public function test_the_extensions_files_stay_locked_from_the_client_until_the_final_balance(): void
    {
        ['client' => $client, 'student' => $student, 'agreement' => $agreement, 'addendum' => $addendum] = $this->executedAddendum();

        $this->payInSimulation($client, $addendum, 1);

        Storage::disk('public')->put('task-proofs/'.$agreement->id.'/build.zip', 'the code');

        $task = AgreementTask::query()->where('addendum_id', $addendum->id)->first();
        $task->update([
            'status' => TaskStatus::Submitted,
            'proof_path' => 'task-proofs/'.$agreement->id.'/build.zip',
            'proof_name' => 'build.zip',
            'proof_url' => 'https://github.com/example/private-repo',
        ]);

        $proof = fn (User $user) => $this->actingAs($user)
            ->get(route('agreements.tasks.proof', $this->asParty($user, $agreement, ['task' => $task])));

        $proof($client)->assertForbidden();
        $proof($student)->assertOk();

        $this->actingAs($client)
            ->get(route('project-management', ['current_team' => $client->currentTeam, 'agreement' => $agreement->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('agreement.phases.0.tasks.0.proofLocked', true)
                ->where('agreement.phases.0.tasks.0.proofHref', null)
                ->where('agreement.phases.0.tasks.0.proofUrl', null)
                ->where('agreement.phases.0.tasks.0.isExtension', true));

        AgreementTask::query()->where('addendum_id', $addendum->id)->update(['status' => TaskStatus::Submitted]);
        $this->payInSimulation($client, $addendum->refresh(), 2);

        $proof($client)->assertOk();
    }

    public function test_the_project_cannot_be_completed_while_the_extension_is_open(): void
    {
        ['client' => $client, 'agreement' => $agreement] = $this->executedAddendum();

        $this->actingAs($client)
            ->post(route('agreements.completion.store', $this->asParty($client, $agreement)), ['rating' => 5])
            ->assertSessionHasErrors(['project' => 'Finish the project extension first, or cancel it if it is not signed yet.']);

        $this->assertSame('in_progress', $agreement->project->refresh()->status->value);
    }

    /**
     * An agreement whose addendum both sides have signed: ₱10,000, two services.
     *
     * @return array{client: User, student: User, agreement: Agreement, addendum: Addendum}
     */
    private function executedAddendum(): array
    {
        $parties = $this->collaboration();

        $addendum = Addendum::factory()->executed(10000)->create([
            'agreement_id' => $parties['agreement']->id,
            'reference' => $parties['agreement']->reference.'-A1',
            'client_signed_by' => $parties['client']->id,
            'student_signed_by' => $parties['student']->id,
        ]);

        foreach (['Post-Deployment Maintenance', 'Feature Enhancements'] as $objective) {
            AddendumService::factory()->create([
                'addendum_id' => $addendum->id,
                'objective' => $objective,
            ]);
        }

        return [...$parties, 'addendum' => $addendum->load('payments')];
    }

    private function payInSimulation(User $client, Addendum $addendum, int $milestone): void
    {
        $this->actingAs($client)
            ->post(route('payments.simulated.store', $addendum->payment($milestone)))
            ->assertRedirect();
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentArgs(User $user, Agreement $agreement, Addendum $addendum, mixed $payment): array
    {
        return $this->asParty($user, $agreement, ['addendum' => $addendum, 'payment' => $payment]);
    }

    private function webhook(string $payload, string $secret): TestResponse
    {
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return $this->call('POST', route('webhooks.paymongo'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature},li=",
        ], $payload);
    }
}
