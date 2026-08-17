<?php

use App\Models\Agreement;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The contract log the agreement screen promises.
 *
 * "Signing records your name, account ID and timestamp to the contract log" is
 * a claim the platform now has to keep, so this table is append-only: rows are
 * never edited and never deleted while the agreement stands. A change request
 * supersedes the whole agreement and the new version collects new signatures,
 * which leaves the old ones readable as history rather than overwriting them.
 *
 * The four acknowledgement checkboxes are stored with the signature rather than
 * on the agreement, because they are a statement by one person at one moment,
 * not a property of the contract.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agreement_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Agreement::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            $table->string('party');

            /* Typed by hand at signing; not copied from the profile, on purpose. */
            $table->string('signed_name');
            $table->json('acknowledgements');

            $table->timestamp('signed_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            /* One signature per side per version of the agreement. */
            $table->unique(['agreement_id', 'party']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agreement_signatures');
    }
};
