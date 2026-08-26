<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A video meeting held against one project conversation.
 *
 * Additive only — nothing existing is touched. A meeting hangs off the
 * conversation rather than the project because the conversation is already the
 * thing that decides who may speak to whom: App\Broadcasting\ConversationChannel
 * answers that question for the socket, and the token endpoint asks it the same
 * way. Hanging meetings off the project instead would mean re-deriving the two
 * sides, and the two answers could drift.
 *
 * Agora keeps no state worth having. A channel exists while somebody is in it
 * and evaporates after — so started_at / ended_at here are the only record that
 * a call happened at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Conversation::class)->constrained()->cascadeOnDelete();

            /** Whoever pressed "start a call" — either side may. */
            $table->foreignIdFor(User::class, 'created_by')->constrained('users')->cascadeOnDelete();

            /*
             * The Agora channel name. Unique because a token is scoped to one
             * channel: two meetings sharing a name would let a token minted for
             * the first join the second.
             *
             * Random rather than derived from the conversation id, so an ended
             * meeting's token cannot be replayed into the next call on the same
             * thread.
             */
            $table->string('channel_name', 64)->unique();

            /** Set when an invitation is sent for a time rather than started now. */
            $table->timestamp('scheduled_at')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            /* The thread's meeting list, newest first. */
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
