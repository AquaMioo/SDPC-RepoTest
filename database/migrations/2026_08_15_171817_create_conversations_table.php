<?php

use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One thread per project-and-student pair.
 *
 * @see Conversation::isUnreadFor()
 *
 * Messaging is deliberately not a free-form address book. A thread exists only
 * where an application already links a student to a posting, which means every
 * conversation has a subject, both sides know why the other is writing, and no
 * student can be messaged by a business that has no dealings with them.
 *
 * The client side is the project's team rather than one person, so a colleague
 * can pick up a thread without the history being locked to whoever started it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Project::class)->constrained()->cascadeOnDelete();
            /** The student half of the thread. */
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            /** Denormalised so the thread list can sort without aggregating. */
            $table->timestamp('last_message_at')->nullable()->index();

            /*
             * Read state per side rather than per message: a thread has
             * exactly two sides, so two columns answer "is there anything new
             * for me" without a pivot table or a row per recipient.
             *
             * Held as the last message each side has seen rather than a
             * timestamp. Laravel stores datetimes to the second, so a reply
             * landing in the same second as the other side's last read is
             * indistinguishable from one they had already seen — which read as
             * "no new messages" when the two happened quickly. Message ids are
             * monotonic and have no such tie.
             */
            $table->unsignedBigInteger('client_read_message_id')->nullable();
            $table->unsignedBigInteger('student_read_message_id')->nullable();

            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
