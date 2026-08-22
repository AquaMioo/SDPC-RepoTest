<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The emailed codes that prove somebody can open the address they typed.
 *
 * Keyed on the address rather than on a user, because at the two moments this
 * table is read there is no usable account behind it: registration has not
 * created one yet, and an appeal comes from an account that cannot sign in.
 *
 * One live code per address and purpose. Asking for another replaces the first
 * rather than leaving two doors open, which is also what makes the resend
 * floor enforceable — `sent_at` is on the row being replaced.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('one_time_passwords', function (Blueprint $table) {
            $table->id();

            $table->string('email')->index();
            $table->string('purpose');

            /* Hashed: a leaked database row must not be a working code. */
            $table->string('code_hash');

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('sent_at');

            $table->timestamps();

            $table->unique(['email', 'purpose']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('one_time_passwords');
    }
};
