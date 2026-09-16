<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Hold every group chat to five people: a team of four and the client.
 *
 * A thread belongs to one student. When it names a team, that student is
 * meant to be one of the team's four. Before Conversation::releaseFromTeam(),
 * a student who left or was voted off a team kept their thread attached to it,
 * so the chat carried them plus the team's four once the seat was filled — six.
 *
 * This applies the same rule, once, to threads that were left that way: the
 * thread falls back to its student alone. No message or membership is touched,
 * and a student who later forms a group again has it adopted as usual.
 */
return new class extends Migration
{
    public function up(): void
    {
        $released = DB::table('conversations')
            ->whereNotNull('student_team_id')
            ->whereNotExists(fn (Builder $membership) => $membership
                ->selectRaw('1')
                ->from('team_members')
                ->whereColumn('team_members.team_id', 'conversations.student_team_id')
                ->whereColumn('team_members.user_id', 'conversations.user_id'))
            ->update(['student_team_id' => null]);

        if ($released > 0) {
            Log::info('Group chats whose student had left the team were released.', [
                'conversations' => $released,
            ]);
        }
    }

    /**
     * Nothing to put back: the threads were attached to a team their student
     * was no longer on.
     */
    public function down(): void
    {
        //
    }
};
