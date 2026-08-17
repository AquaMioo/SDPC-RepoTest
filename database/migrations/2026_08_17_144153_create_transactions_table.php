<?php

use App\Enums\PaymentWallet;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The money ledger behind the Transaction screen.
 *
 * Built, migrated and tested, but shipped switched off: `config('billing.enabled')`
 * is false by default, the routes 404 while it is, and App\Actions\Billing\
 * RecordTransaction writes nothing. Turning it on is one environment variable —
 * no schema change, no missing table, no half-finished feature to discover.
 *
 * There is no payment gateway here on purpose. A row is a record of a transfer
 * the two parties made themselves; the platform observes it rather than moving
 * the money, which is what keeps this free of any new dependency.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Agreement::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(AgreementMilestone::class, 'agreement_milestone_id')
                ->nullable()
                ->constrained('agreement_milestones')
                ->nullOnDelete();
            $table->foreignIdFor(Project::class)->constrained()->cascadeOnDelete();

            $table->foreignIdFor(Team::class, 'payer_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'payee_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('type')->default(TransactionType::Milestone->value)->index();
            $table->string('status')->default(TransactionStatus::Pending->value)->index();
            $table->string('wallet')->default(PaymentWallet::Unset->value);

            /* Whole pesos, matching agreement_milestones.amount. */
            $table->unsignedInteger('amount');
            $table->string('description');
            $table->string('reference')->unique();

            /* The work the payment covers, which is not when it was paid. */
            $table->date('benefit_period_start')->nullable();
            $table->date('benefit_period_end')->nullable();

            $table->timestamp('posted_at')->nullable();
            $table->timestamp('settled_at')->nullable();

            $table->timestamps();

            $table->index(['payee_user_id', 'status']);
            $table->index(['payer_team_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
