<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which message this one answers.
 *
 * Self-referencing and nullable: most messages reply to nothing. nullOnDelete
 * rather than cascade, because a reply is still a message somebody sent — if
 * the line it answered is deleted outright the reply must survive, quoting
 * nothing, instead of disappearing with it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('reply_to_message_id')
                ->nullable()
                ->after('user_id')
                ->constrained('messages')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'reply_to_message_id')) {
                $table->dropForeign(['reply_to_message_id']);
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'reply_to_message_id')) {
                $table->dropColumn('reply_to_message_id');
            }
        });
    }
};
