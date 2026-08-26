<?php

namespace App\Http\Controllers\Messaging;

use App\Actions\Messaging\AnnounceMeeting;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Meeting;
use App\Models\Team;
use App\Services\Agora\RtcTokenBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;

/**
 * Video meetings on a project conversation.
 *
 * Every method gates on the conversation's own participant check, the same one
 * ConversationController and App\Broadcasting\ConversationChannel use. A call
 * is another door into the thread, so it answers to the same rule — Agora
 * itself has no idea who belongs in a channel, and a token is the only thing
 * standing between a channel name and anybody who has it.
 *
 * The module is absent rather than half-built when config('agora.enabled') is
 * false: the routes 404, so nothing offers a call the platform cannot place.
 */
class MeetingController extends Controller
{
    public function __construct(private readonly AnnounceMeeting $announce) {}

    /**
     * Open a meeting on a thread and tell the other side it is happening.
     */
    public function store(Request $request, Team $currentTeam, Conversation $conversation): JsonResponse
    {
        $this->ensureEnabled();

        $user = $request->user();

        abort_unless($conversation->isParticipant($user), HttpResponse::HTTP_FORBIDDEN);

        $meeting = DB::transaction(fn (): Meeting => $conversation->meetings()->create([
            'created_by' => $user->id,
            'channel_name' => Meeting::newChannelName(),
            'started_at' => now(),
        ]));

        /*
         * A courtesy on top of a write that already succeeded, exactly as
         * AnnounceMessage treats MessageSent: the invitation failing to reach
         * the other side must never fail the call for the person placing it.
         */
        $this->announce->handle($meeting);

        return response()->json([
            'meeting' => $this->present($meeting),
            'token' => $this->tokenFor($meeting, $user->id),
        ], HttpResponse::HTTP_CREATED);
    }

    /**
     * Issue a token for joining a meeting already open.
     *
     * Separate from store() because the other side joins a call they did not
     * create, and because a token expires while a meeting may outlive it.
     */
    public function token(Request $request, Team $currentTeam, Meeting $meeting): JsonResponse
    {
        $this->ensureEnabled();

        $user = $request->user();

        abort_unless($meeting->isParticipant($user), HttpResponse::HTTP_FORBIDDEN);
        abort_unless($meeting->isJoinable(), HttpResponse::HTTP_GONE);

        return response()->json([
            'meeting' => $this->present($meeting),
            'token' => $this->tokenFor($meeting, $user->id),
        ]);
    }

    /**
     * Close a meeting. Either participant may.
     */
    public function end(Request $request, Team $currentTeam, Meeting $meeting): JsonResponse
    {
        $this->ensureEnabled();

        abort_unless($meeting->isParticipant($request->user()), HttpResponse::HTTP_FORBIDDEN);

        /* Idempotent: two people hanging up together is the normal case. */
        if ($meeting->ended_at === null) {
            $meeting->forceFill(['ended_at' => now()])->save();
        }

        return response()->json(['meeting' => $this->present($meeting->fresh())]);
    }

    /**
     * Mint a channel-scoped, user-scoped token.
     */
    private function tokenFor(Meeting $meeting, int $uid): array
    {
        $builder = new RtcTokenBuilder(
            (string) config('agora.app_id'),
            (string) config('agora.app_certificate'),
        );

        $ttl = (int) config('agora.token_ttl');

        return [
            'appId' => (string) config('agora.app_id'),
            'channel' => $meeting->channel_name,
            'uid' => $uid,
            'token' => $builder->build($meeting->channel_name, $uid, $ttl),
            'expiresIn' => $ttl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Meeting $meeting): array
    {
        return [
            'id' => $meeting->id,
            'conversationId' => $meeting->conversation_id,
            'channel' => $meeting->channel_name,
            'createdBy' => $meeting->created_by,
            'startedAt' => $meeting->started_at?->toIso8601String(),
            'endedAt' => $meeting->ended_at?->toIso8601String(),
        ];
    }

    /**
     * Without credentials there is no call to place, so the routes are absent.
     */
    private function ensureEnabled(): void
    {
        abort_unless(
            (bool) config('agora.enabled')
                && filled(config('agora.app_id'))
                && filled(config('agora.app_certificate')),
            HttpResponse::HTTP_NOT_FOUND,
        );
    }
}
