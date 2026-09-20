<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Remove for you": one account hiding one message from its own view.
 *
 * Kept apart from messages.removed_at, which is the sender taking a message
 * back from everybody. This is the other kind of removal every chat app
 * offers — the line stays in the thread for the people it was sent to, and
 * disappears only for the person who asked.
 *
 * A row per (message, viewer), so anybody in a group thread may hide anybody's
 * message for themselves without touching what the others see.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('message_hides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['message_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_hides');
    }
};
