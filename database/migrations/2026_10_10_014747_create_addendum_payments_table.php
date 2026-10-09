<?php

use App\Models\Addendum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section IV's two milestones and the record of how each was paid.
 *
 * Written when the addendum is executed (both signatures): Milestone 1, the
 * 30% down payment, and Milestone 2, the 70% final balance. Each keeps the
 * gateway's checkout session and payment ids (pay_xxxx) beside SDPC's own
 * invoice number, which is the audit trail Section V describes and the
 * transaction record both parties can print.
 *
 * Amounts are centavos, the unit PayMongo takes.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('addendum_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Addendum::class)->constrained('addenda')->cascadeOnDelete();
            $table->unsignedTinyInteger('milestone');
            $table->unsignedTinyInteger('percentage');
            $table->unsignedInteger('amount');
            $table->string('status');
            $table->string('invoice_number')->unique();

            $table->string('gateway')->nullable();
            $table->string('checkout_session_id')->nullable()->index();
            $table->string('provider_payment_id')->nullable();
            $table->string('payment_method')->nullable();

            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['addendum_id', 'milestone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addendum_payments');
    }
};
