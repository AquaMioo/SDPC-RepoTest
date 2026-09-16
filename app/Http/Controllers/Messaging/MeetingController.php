<?php

namespace App\Http\Controllers\Messaging;

use App\Actions\Messaging\AnnounceMeeting;
use App\Actions\Messaging\RingParticipants;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\OpenMeetingRequest;
use App\Models\Conversation;
use App\Models\Meeting;
use App\Models\Team;
use App\Models\User;
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
    public function __construct(
        private readonly AnnounceMeeting $announce,
        private readonly RingParticipants $ring,
    ) {}

    /**
     * Open a meeting on a thread, now or for later.
     *
     * One endpoint for both because they create the same row — the only
     * difference is whether anybody is in it yet. A call started now comes
     * back with a token because the caller is about to join it; a meeting
     * booked for later does not, because there is nothing to join and a token
     * minted now would have expired by the time there was.
     *
     * Pressing Call while somebody is already in a call on the thread joins
     * that call. In a group thread two people pressing Call used to open two
     * separate calls, and the group split between them without knowing.
     */
    public function store(OpenMeetingRequest $request, Team $currentTeam, Conversation $conversation): JsonResponse
    {
        $this->ensureEnabled();

        $user = $request->user();

        abort_unless($conversation->isParticipant($user), HttpResponse::HTTP_FORBIDDEN);

        $scheduledAt = $request->validated('scheduled_at');

        /** @var array{0: Meeting, 1: bool} $opened */
        $opened = DB::transaction(function () use ($conversation, $user, $scheduledAt): array {
            if ($scheduledAt === null) {
                /*
                 * Locked on the thread, so two people pressing Call at the
                 * same moment end up in one call rather than one each.
                 */
                Conversation::query()->whereKey($conversation->id)->lockForUpdate()->first();

                $running = $conversation->meetings()->inProgress()->first();

                if ($running !== null) {
                    $running->markPresent($user);

                    return [$running, false];
                }
            }

            $meeting = $conversation->meetings()->create([
                'created_by' => $user->id,
                'channel_name' => Meeting::newChannelName(),
                'scheduled_at' => $scheduledAt,
                'started_at' => $scheduledAt === null ? now() : null,
            ]);

            if (! $meeting->isScheduled()) {
                $meeting->markPresent($user);
            }

            return [$meeting, true];
        });

        [$meeting, $isNew] = $opened;

        /* Joining a call already ringing rings nobody a second time. */
        if (! $isNew) {
            return response()->json($this->joinPayload($meeting, $user->id));
        }

        /*
         * A courtesy on top of a write that already succeeded, exactly as
         * AnnounceMessage treats MessageSent: the invitation failing to reach
         * the other side must never fail the call for the person placing it.
         */
        $this->announce->handle($meeting);

        /*
         * A call started now rings everyone else in the thread, wherever they
         * are on the platform. A booked meeting is a diary entry, not a ring.
         */
        if (! $meeting->isScheduled()) {
            $this->ring->ring($meeting, $user);
        }

        if ($meeting->isScheduled()) {
            return response()->json([
                'meeting' => $this->present($meeting),
                'token' => null,
            ], HttpResponse::HTTP_CREATED);
        }

        return response()->json(
            $this->joinPayload($meeting, $user->id),
            HttpResponse::HTTP_CREATED,
        );
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

        /*
         * Joining a booked meeting is what starts it. Recorded here rather
         * than on a schedule, because a meeting nobody turned up to should not
         * read as one that ran — started_at means somebody was there.
         */
        if ($meeting->started_at === null) {
            $meeting->forceFill(['started_at' => now()])->save();
        }

        $meeting->markPresent($user);

        return response()->json($this->joinPayload($meeting->fresh(), $user->id));
    }

    /**
     * Still here.
     *
     * The call screen calls this every 20 seconds. A tab that is closed, or a
     * connection that drops, never says goodbye; the heartbeat stopping is
     * how the platform finds out, so a call nobody is left in stops being
     * offered to join.
     *
     * 410 once the call is over, which tells the screen to close.
     */
    public function heartbeat(Request $request, Team $currentTeam, Meeting $meeting): HttpResponse
    {
        $this->ensureEnabled();

        $user = $request->user();

        abort_unless($meeting->isParticipant($user), HttpResponse::HTTP_FORBIDDEN);
        abort_unless($meeting->isJoinable(), HttpResponse::HTTP_GONE);

        $meeting->markPresent($user);

        return response()->noContent();
    }

    /**
     * Leave a call, leaving it running for everyone still in it.
     *
     * The call ends only when the last person leaves — and then whoever is
     * still being rung for it stops ringing, which is also what happens when
     * a caller gives up before anybody answers.
     */
    public function leave(Request $request, Team $currentTeam, Meeting $meeting): JsonResponse
    {
        $this->ensureEnabled();

        $user = $request->user();

        abort_unless($meeting->isParticipant($user), HttpResponse::HTTP_FORBIDDEN);

        if ($meeting->markLeft($user)) {
            $this->ring->hangUp($meeting, $user);
        }

        return response()->json(['meeting' => $this->present($meeting->fresh())]);
    }

    /**
     * Close a meeting for everyone. Any participant may.
     */
    public function end(Request $request, Team $currentTeam, Meeting $meeting): JsonResponse
    {
        $this->ensureEnabled();

        abort_unless($meeting->isParticipant($request->user()), HttpResponse::HTTP_FORBIDDEN);

        /* Idempotent: two people hanging up together is the normal case. */
        if ($meeting->ended_at === null) {
            $meeting->forceFill(['ended_at' => now()])->save();

            /* Whoever is still being rung for it should stop ringing. */
            $this->ring->hangUp($meeting, $request->user());
        }

        return response()->json(['meeting' => $this->present($meeting->fresh())]);
    }

    /**
     * What somebody joining a call needs: the call, their token, and who else
     * might turn up in it.
     *
     * `people` names the tiles. Agora only knows each person by uid (the user
     * id), and everyone listed can already read every name in the thread.
     *
     * @return array{meeting: array<string, mixed>, token: array<string, mixed>, people: list<array{uid: int, name: string}>}
     */
    private function joinPayload(Meeting $meeting, int $uid): array
    {
        return [
            'meeting' => $this->present($meeting),
            'token' => $this->tokenFor($meeting, $uid),
            'people' => $meeting->conversation
                ->participants()
                ->map(fn (User $person): array => ['uid' => $person->id, 'name' => $person->name])
                ->values()
                ->all(),
        ];
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
            'scheduledAt' => $meeting->scheduled_at?->toIso8601String(),
            'isScheduled' => $meeting->isScheduled(),
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
