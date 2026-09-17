<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who, besides the student a thread belongs to, is in its group chat.
 *
 * A group chat used to be the whole student team: attaching the team to a
 * thread let every member read it, including anyone who joined the team
 * later. Now the team's creator invites teammates one by one, and this table
 * is the list. Membership is still checked against the team on every read,
 * so somebody who leaves the team loses the chat even before their row goes.
 *
 * Existing group chats are carried over as they stand: everyone on the team
 * today is written in, so nobody loses a conversation they were already in.
 * The creator can remove people from there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Conversation::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            /** The team creator who added them; null for the carried-over rows. */
            $table->foreignIdFor(User::class, 'invited_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });

        $now = now();

        DB::table('conversations')
            ->join('teams', 'teams.id', '=', 'conversations.student_team_id')
            ->join('team_members', 'team_members.team_id', '=', 'conversations.student_team_id')
            ->whereNull('teams.deleted_at')
            ->whereColumn('team_members.user_id', '!=', 'conversations.user_id')
            ->orderBy('conversations.id')
            ->select(['conversations.id as conversation_id', 'team_members.user_id'])
            ->get()
            ->chunk(200)
            ->each(fn ($rows) => DB::table('conversation_members')->insertOrIgnore(
                $rows->map(fn (object $row): array => [
                    'conversation_id' => $row->conversation_id,
                    'user_id' => $row->user_id,
                    'invited_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all(),
            ));
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_members');
    }
};
