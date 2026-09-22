<?php

namespace App\Actions\Messaging;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Put a team into the chat for the build it is working on.
 *
 * Group chats are invite-only everywhere else — the thread's creator chooses
 * who reads it (see .ai/rules/messaging.md). The build's own thread is the
 * exception the testers asked for on 2026-09-23: a teammate is tied to that
 * project, writes its checklist and answers for its deadlines, so being left
 * out of the conversation with the client leaves them working blind.
 *
 * Only that thread. A lead's other conversations — a client who invited them
 * to a posting they never took, a business they are still talking to — stay
 * theirs to share, because none of those are the team's work.
 *
 * Run whenever the pairing changes: a student joins a team already on a build,
 * or a client takes a lead on whose team people are already sitting.
 */
class SeatTeammatesInProjectChat
{
    public function __construct(private readonly NotifyMemberAdded $notifyMemberAdded) {}

    /**
     * Seat the leader's teammates in the chats for the builds they hold.
     *
     * @param  bool  $notify  Tell each teammate and the business. Off for a backfill.
     */
    public function handle(User $holder, bool $notify = true): void
    {
        $teammates = $holder->teammates();
        $team = $holder->ownedTeams()->first();

        if ($teammates->isEmpty() || $team === null) {
            return;
        }

        $builds = Application::query()
            ->where('user_id', $holder->id)
            ->where('status', ApplicationStatus::Accepted)
            ->whereHas('project', fn (Builder $query) => $query->unfinished())
            ->pluck('project_id');

        if ($builds->isEmpty()) {
            return;
        }

        $conversations = Conversation::query()
            ->where('user_id', $holder->id)
            ->whereIn('project_id', $builds)
            ->get();

        foreach ($conversations as $conversation) {
            /*
             * A thread opened before the team existed carries no team, and
             * membership rows only count while the person is on the thread's
             * team (Conversation::isGroupMember). Attach this one, or leave a
             * thread that somehow belongs to another team alone.
             */
            if ($conversation->student_team_id === null) {
                $conversation->forceFill(['student_team_id' => $team->id])->save();
            }

            if ($conversation->student_team_id !== $team->id) {
                continue;
            }

            foreach ($teammates as $teammate) {
                if ($conversation->members()->whereKey($teammate->id)->exists()) {
                    continue;
                }

                $conversation->members()->attach($teammate->id, ['invited_by' => $holder->id]);

                if ($notify) {
                    $this->notifyMemberAdded->handle($conversation, $teammate, $holder);
                }
            }
        }
    }
}
