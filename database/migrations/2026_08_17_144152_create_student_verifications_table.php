<?php

use App\Enums\VerificationProvider;
use App\Enums\VerificationStatus;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A third party's answer to "is this person really a student?".
 *
 * Deliberately separate from `student_credentials`, which is the document an
 * administrator reads and the only thing that actually grants a verified
 * account. This table is additive: a verified row earns a badge and gives a
 * reviewer supporting evidence, and nothing on the platform is gated on it.
 *
 * Kept provider-agnostic — SheerID is the first integration, not the schema.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            $table->string('provider')->default(VerificationProvider::SheerId->value);
            $table->string('status')->default(VerificationStatus::Pending->value)->index();

            /* The provider's own identifier for the verification attempt. */
            $table->string('external_id')->nullable()->index();
            $table->string('redirect_url', 2048)->nullable();

            $table->timestamp('verified_at')->nullable();
            $table->string('failure_reason')->nullable();

            /* Whatever the provider sent back, for a human to inspect later. */
            $table->json('payload')->nullable();

            $table->timestamps();

            /* One live verification per student per provider. */
            $table->unique(['user_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_verifications');
    }
};
