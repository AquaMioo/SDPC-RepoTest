<?php

use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Enums\TeamRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Put the teams that are already building into the chat with their client.
 *
 * From 2026-09-23 a teammate is seated in the build's thread automatically
 * (App\Actions\Messaging\SeatTeammatesInProjectChat). The teams already on a
 * build when that shipped were invited one by one or not at all, so this
 * writes the rows they would have had. Written as plain queries rather than
 * through the action, so it keeps doing what it did today whatever the action
 * grows into, and it tells nobody: these are chats people are already in.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        /* The threads of students who hold a build, with the team they lead. */
        $threads = DB::table('conversations')
            ->join('applications', function ($join) {
                $join->on('applications.project_id', '=', 'conversations.project_id')
                    ->on('applications.user_id', '=', 'conversations.user_id');
            })
            ->join('projects', 'projects.id', '=', 'conversations.project_id')
            ->join('team_members as leader', function ($join) {
                $join->on('leader.user_id', '=', 'conversations.user_id')
                    ->where('leader.role', '=', TeamRole::Owner->value);
            })
            ->where('applications.status', ApplicationStatus::Accepted->value)
            ->whereIn('projects.status', array_map(
                fn (ProjectStatus $status): string => $status->value,
                array_values(array_filter(
                    ProjectStatus::cases(),
                    fn (ProjectStatus $status): bool => $status->isUnfinished(),
                )),
            ))
            ->select([
                'conversations.id as conversation_id',
                'conversations.user_id as holder_id',
                'conversations.student_team_id',
                'leader.team_id as owned_team_id',
            ])
            ->get();

        foreach ($threads as $thread) {
            /* A thread opened before the team existed carries no team yet. */
            if ($thread->student_team_id === null) {
                DB::table('conversations')
                    ->where('id', $thread->conversation_id)
                    ->update(['student_team_id' => $thread->owned_team_id]);

                $thread->student_team_id = $thread->owned_team_id;
            }

            if ((int) $thread->student_team_id !== (int) $thread->owned_team_id) {
                continue;
            }

            $teammates = DB::table('team_members')
                ->where('team_id', $thread->owned_team_id)
                ->where('user_id', '!=', $thread->holder_id)
                ->pluck('user_id');

            foreach ($teammates as $teammate) {
                $seated = DB::table('conversation_members')
                    ->where('conversation_id', $thread->conversation_id)
                    ->where('user_id', $teammate)
                    ->exists();

                if ($seated) {
                    continue;
                }

                DB::table('conversation_members')->insert([
                    'conversation_id' => $thread->conversation_id,
                    'user_id' => $teammate,
                    'invited_by' => $thread->holder_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Nothing to undo: the rows are indistinguishable from the ones a team
     * lead adds by hand, and dropping them would take people out of chats
     * they have been reading.
     */
    public function down(): void
    {
        //
    }
};
