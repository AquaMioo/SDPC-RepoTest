<?php

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\Meeting;
use App\Models\User;

/**
 * The meetings somebody has booked, soonest first, for their dashboard.
 *
 * A meeting scheduled in a thread used to live only in that thread — the one
 * place nobody looks when they are planning their week. Both dashboards read
 * this, so a client and a student see the same booking from their own side.
 *
 * Only threads the person can still open count. A thread a signed agreement
 * has closed off is not something they can act on, and a dashboard card
 * pointing at it would lead straight to the inbox redirect.
 *
 * The time is sent as an ISO-8601 instant and nothing more. Whether it reads
 * "Today" or "Tomorrow" depends on the viewer's own clock: the application runs
 * on UTC, so a meeting at 7:00 in the morning in Manila is still the previous
 * day in UTC, and wording it here would put it on the wrong day.
 */
class UpcomingMeetings
{
    /**
     * @return list<array{id: int, scheduledAt: string, with: string, project: string, scheduledByMe: bool, url: string}>
     */
    public function handle(User $user, int $limit = 5): array
    {
        $team = $user->currentTeam;

        if ($team === null) {
            return [];
        }

        $visible = Conversation::query()->visibleTo($user)->select('conversations.id');

        return Meeting::query()
            ->upcoming()
            ->whereIn('conversation_id', $visible)
            ->with(['conversation.project.team.clientProfile', 'conversation.student'])
            ->limit($limit)
            ->get()
            ->map(fn (Meeting $meeting): array => [
                'id' => $meeting->id,
                'scheduledAt' => $meeting->scheduled_at->toIso8601String(),
                'with' => $this->counterpart($meeting->conversation, $user),
                'project' => $meeting->conversation->project->title,
                'scheduledByMe' => $meeting->created_by === $user->id,
                'url' => route('messages.show', [
                    'current_team' => $team->slug,
                    'conversation' => $meeting->conversation_id,
                ]),
            ])
            ->values()
            ->all();
    }

    /**
     * Who the meeting is with, from this person's side of the thread.
     *
     * Read off the business rather than off who the thread's student is, so a
     * student's teammate — who is not the thread's user_id — is still told the
     * business's name and not their own leader's.
     */
    protected function counterpart(Conversation $conversation, User $user): string
    {
        $business = $conversation->project->team;

        if ($business->id === $user->current_team_id) {
            return $conversation->student->name;
        }

        return $business->clientProfile?->business_name ?? $business->name;
    }
}
