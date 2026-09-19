import { Head, router, useForm, usePoll } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import {
    CalendarPlusIcon,
    ImageIcon,
    PaperPlaneRightIcon,
    SmileyIcon,
    UsersThreeIcon,
    VideoCameraIcon,
} from '@phosphor-icons/react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

import EmptyInbox from '@/components/messaging/empty-inbox';
import VideoCall from '@/components/messaging/video-call';
import type {
    MeetingCredentials,
    MeetingPerson,
} from '@/components/messaging/video-call';
import { Btn } from '@/components/sdpc/btn';
import { Input } from '@/components/sdpc/input';
import { Panel, PanelKicker } from '@/components/sdpc/panel';
import { useCurrentTeam } from '@/hooks/use-current-team';
import {
    heartbeat as meetingHeartbeat,
    leave as leaveMeeting,
    store as startMeeting,
    token as meetingToken,
} from '@/routes/meetings';
import {
    edit as editMessage,
    react as reactToMessage,
    remove as removeMessageRoute,
    send as sendMessage,
    show as showThread,
} from '@/routes/messages';
import {
    destroy as removeFromChat,
    store as inviteToChat,
} from '@/routes/messages/members';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

type Thread = {
    id: number;
    title: string;
    subtitle: string;
    preview: string;
    at: string | null;
    isUnread: boolean;
    isActive: boolean;
};

/** The group-chat panel at the foot of the thread list. */
type GroupState = {
    /** At least one teammate has been invited into the open thread. */
    isGroup: boolean;
    teamName: string | null;
    /** The viewer created the thread's team, so they choose who is in. */
    canManage: boolean;
    /** The viewer owns the thread and has no team to invite from yet. */
    needsTeam: boolean;
    isThreadOwner: boolean;
    /** The student side of the chat: the thread's student, then invitees. */
    people: {
        id: number;
        name: string;
        isCreator: boolean;
        isThreadOwner: boolean;
    }[];
    /** Teammates the creator can still invite. */
    invitable: { id: number; name: string }[];
};

type Props = {
    /** False when the platform holds no Agora credentials. */
    videoEnabled: boolean;
    group: GroupState;
    threads: Thread[];
    active: {
        id: number;
        title: string;
        project: string;
        meetings: {
            id: number;
            scheduledAt: string | null;
            scheduledFor: string | null;
            isMine: boolean;
        }[];
        /** A call somebody is in right now, and who. */
        call: { id: number; people: string[] } | null;
        messages: {
            id: number;
            body: string | null;
            author: string;
            isMine: boolean;
            at: string | null;
            isEdited: boolean;
            isRemoved: boolean;
            editableUntil: number | null;
            imageUrl: string | null;
            reactions: { emoji: string; count: number; reacted: boolean }[];
        }[];
        reactionChoices: string[];
        /** Null until there is a signed agreement to report on. */
        intel: {
            title: string | null;
            progress: number;
            due: string | null;
            open: number;
            reference: string;
        } | null;
    } | null;
};

/** A short row of emoji for the composer, not a full keyboard. */
const QUICK_EMOJI = [
    '😀',
    '😂',
    '🙏',
    '👍',
    '🎉',
    '🔥',
    '❤️',
    '😢',
    '👀',
    '✅',
];

/**
 * Whether a message is nothing but a few emoji.
 *
 * Those get drawn large and without a bubble, the way every chat app does it —
 * a lone 🔥 sitting in a full-width panel reads as a mistake. Capped at a
 * handful so a wall of emoji stays a normal message rather than filling the
 * thread.
 */
function isEmojiOnly(message: {
    body: string | null;
    imageUrl: string | null;
}) {
    if (message.imageUrl !== null || message.body === null) {
        return false;
    }

    const text = message.body.trim();

    if (text === '') {
        return false;
    }

    // Extended_Pictographic covers emoji proper; the rest are the joiners,
    // skin-tone modifiers and variation selectors that compose them.
    const emojiOnly =
        /^(\p{Extended_Pictographic}|\p{Emoji_Component}|‍|️|\s)+$/u;

    return (
        emojiOnly.test(text) &&
        [...new Intl.Segmenter().segment(text)].length <= 3
    );
}

/**
 * How an action on one message goes out: re-read the thread and the list, and
 * leave everything else on the page alone.
 *
 * Without `only` and preserveState a router visit is a whole page load as far
 * as the screen is concerned. Every prop was rebuilt, NavigationSkeleton drew
 * over the thread once it passed 300ms, and the page's own state was thrown
 * away — the open picker, a half-typed draft, even a video call in progress.
 * That was the "reload" on every reaction.
 */
const IN_PLACE = {
    preserveScroll: true,
    preserveState: true,
    only: ['threads', 'active'],
};

/** How far ahead a call may be booked; OpenMeetingRequest refuses later. */
const MEETING_WINDOW_DAYS = 90;

/** A moment as a datetime-local input writes it: local time, to the minute. */
function toDateTimeLocal(moment: Date): string {
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${moment.getFullYear()}-${pad(moment.getMonth() + 1)}-${pad(moment.getDate())}T${pad(moment.getHours())}:${pad(moment.getMinutes())}`;
}

/**
 * The span the "Meet at" box accepts: from now to the end of the booking
 * window. Bounding it also stops the year at four digits — unbounded, a date
 * box lets the year run to six, and "2026" typed once too often became 20266
 * (QA 2026-09-19).
 */
function meetingWindow(): { min: string; max: string } {
    const now = new Date();
    const last = new Date(now.getTime() + MEETING_WINDOW_DAYS * 86_400_000);

    return { min: toDateTimeLocal(now), max: toDateTimeLocal(last) };
}

type Reaction = { emoji: string; count: number; reacted: boolean };

/**
 * The same toggle ConversationController::react makes, applied ahead of it.
 *
 * Pressing an emoji you have already left takes yours away (and the chip with
 * it when nobody else is left on it); anything else adds yours. A new emoji
 * joins at the end, which is where the server's grouping puts it too, so the
 * row does not jump when the real answer arrives.
 */
function toggleReaction(reactions: Reaction[], emoji: string): Reaction[] {
    const existing = reactions.find((reaction) => reaction.emoji === emoji);

    if (existing === undefined) {
        return [...reactions, { emoji, count: 1, reacted: true }];
    }

    return reactions
        .map((reaction) =>
            reaction.emoji !== emoji
                ? reaction
                : {
                      ...reaction,
                      count: reaction.count + (reaction.reacted ? -1 : 1),
                      reacted: !reaction.reacted,
                  },
        )
        .filter((reaction) => reaction.count > 0);
}

/**
 * Messaging, shared by both modules.
 *
 * A thread exists per posting and student, so the list is a list of working
 * relationships rather than an address book. New messages arrive by polling —
 * there is no websocket server in this stack, and a five second poll is
 * honest about that rather than pretending to be live.
 */
export default function Messages({
    videoEnabled,
    group,
    threads,
    active,
}: Props) {
    /* Filters what is already on screen; it never asks the server. */
    const [find, setFind] = useState('');
    /* An invite or removal on its way to the server. */
    const [groupBusy, setGroupBusy] = useState(false);
    /* The message whose Remove is waiting for a yes. */
    const [confirmingRemoval, setConfirmingRemoval] = useState<number | null>(
        null,
    );
    const team = useCurrentTeam();
    const endRef = useRef<HTMLDivElement>(null);
    const scrollRef = useRef<HTMLDivElement>(null);
    const imageRef = useRef<HTMLInputElement>(null);

    /*
     * The thread arrives over a socket, so a message shows up as it is sent
     * rather than on the next tick of a timer.
     *
     * The poll stays as a backstop at a much longer interval: Reverb is a
     * separate process someone has to start, and a chat that silently stops
     * updating when it is not running is worse than one that is briefly slow.
     */
    usePoll(30000, { only: ['threads', 'active'] });

    /*
     * The call this screen is in, if any.
     *
     * A call running on the open thread arrives as `active.call`, re-read
     * when the thread's channel says one started. It deliberately carries no
     * token: joining asks the server for one, where the participant check
     * runs again against the authenticated user rather than against whoever
     * the socket happens to belong to.
     */
    const [call, setCall] = useState<{
        meetingId: number;
        credentials: MeetingCredentials;
        people: MeetingPerson[];
    } | null>(null);
    const [dismissedCall, setDismissedCall] = useState<number | null>(null);
    const [callBusy, setCallBusy] = useState(false);
    const [scheduling, setScheduling] = useState(false);
    const [scheduledAt, setScheduledAt] = useState('');

    /* The running call to offer, unless this person waved it away. */
    const runningCall =
        active?.call && active.call.id !== dismissedCall ? active.call : null;

    /**
     * These endpoints answer in JSON rather than with an Inertia page, so they
     * are fetched directly. Laravel checks X-XSRF-TOKEN against the cookie it
     * set, which is why the header is read back out of document.cookie.
     */
    const sendJson = (
        url: string,
        method = 'POST',
        body?: Record<string, unknown>,
        keepalive = false,
    ) => {
        const xsrf = document.cookie
            .split('; ')
            .find((entry) => entry.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        return fetch(url, {
            method,
            credentials: 'same-origin',
            /* Lets a leave sent while the tab is closing still arrive. */
            keepalive,
            body: body === undefined ? undefined : JSON.stringify(body),
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(xsrf ?? ''),
            },
        });
    };

    const postJson = async (
        url: string,
        method = 'POST',
        body?: Record<string, unknown>,
    ) => {
        const response = await sendJson(url, method, body);

        if (!response.ok) {
            throw new Error(
                `The call could not be placed (${response.status}).`,
            );
        }

        return response.json();
    };

    /** Start a call, or join the one already running on the thread. */
    const startCall = async () => {
        if (active === null || callBusy) {
            return;
        }

        setCallBusy(true);

        try {
            const body = await postJson(
                startMeeting.url({
                    current_team: team.slug,
                    conversation: active.id,
                }),
            );

            setCall({
                meetingId: body.meeting.id,
                credentials: body.token as MeetingCredentials,
                people: body.people as MeetingPerson[],
            });
        } catch {
            /* Left to the caller to retry; nothing has been created. */
        } finally {
            setCallBusy(false);
        }
    };

    /*
     * While in a call: say so every 20 seconds, so the platform knows the
     * call is still running (MeetingAttendee::PRESENCE_WINDOW is 90), and
     * say goodbye if the tab is closed. A 410 means somebody ended the call
     * for everyone, so the screen closes.
     */
    const inCallId = call?.meetingId ?? null;

    useEffect(() => {
        if (inCallId === null) {
            return;
        }

        const args = { current_team: team.slug, meeting: inCallId };

        const beat = async () => {
            try {
                const response = await sendJson(meetingHeartbeat.url(args));

                if (response.status === 410) {
                    setCall(null);
                    router.reload({ only: ['active'] });
                }
            } catch {
                /* A dropped beat is what the window allows for. */
            }
        };

        void beat();
        const timer = window.setInterval(() => void beat(), 20_000);

        const onPageHide = () => {
            void sendJson(leaveMeeting.url(args), 'PATCH', undefined, true);
        };

        window.addEventListener('pagehide', onPageHide);

        return () => {
            window.clearInterval(timer);
            window.removeEventListener('pagehide', onPageHide);
        };
    }, [inCallId, team.slug]);

    /**
     * Leave the call, leaving it running for anyone still in it. The server
     * ends it if this was the last person.
     */
    const leaveCall = () => {
        if (call !== null) {
            void sendJson(
                leaveMeeting.url({
                    current_team: team.slug,
                    meeting: call.meetingId,
                }),
                'PATCH',
                undefined,
                true,
            )
                .catch(() => undefined)
                .finally(() => router.reload({ only: ['active'] }));
        }

        setCall(null);
    };

    const scheduleCall = async () => {
        if (active === null || scheduledAt === '' || callBusy) {
            return;
        }

        setCallBusy(true);

        try {
            await postJson(
                startMeeting.url({
                    current_team: team.slug,
                    conversation: active.id,
                }),
                'POST',
                /*
                 * The picker gives local wall-clock time with no zone. Sending
                 * the browser's own offset means the server stores the instant
                 * the person meant, not the same digits in UTC.
                 */
                { scheduled_at: new Date(scheduledAt).toISOString() },
            );

            setScheduling(false);
            setScheduledAt('');

            /* The booked call belongs in the thread, so re-read it. */
            router.reload({ only: ['active'] });
        } catch {
            /* Validation refused it; the form stays open with the value in it. */
        } finally {
            setCallBusy(false);
        }
    };

    const joinCall = async (meetingId: number) => {
        if (callBusy) {
            return;
        }

        setCallBusy(true);

        try {
            const body = await postJson(
                meetingToken.url({
                    current_team: team.slug,
                    meeting: meetingId,
                }),
            );

            setCall({
                meetingId: body.meeting.id,
                credentials: body.token as MeetingCredentials,
                people: body.people as MeetingPerson[],
            });
        } catch {
            /* The call may have ended between the invitation and the tap. */
            router.reload({ only: ['active'] });
        } finally {
            setCallBusy(false);
        }
    };

    /*
     * "Answer" on the ringing card (IncomingCallAlert) lands here with
     * ?join=<meeting>. Join once, then drop the parameter so a refresh does
     * not join again. The token is still asked for over HTTP, behind the
     * thread's participant check.
     */
    const answeredFromRing = useRef(false);

    useEffect(() => {
        const url = new URL(window.location.href);
        const meetingId = Number(url.searchParams.get('join'));

        if (!Number.isInteger(meetingId) || meetingId <= 0) {
            return;
        }

        /*
         * Deferred, and guarded inside the callback: StrictMode mounts the
         * effect twice, and the first timer is cleared before it fires, so
         * the guard has to be set by the run that actually joins.
         */
        const timer = window.setTimeout(() => {
            if (answeredFromRing.current) {
                return;
            }

            answeredFromRing.current = true;
            url.searchParams.delete('join');
            window.history.replaceState(window.history.state, '', url);

            void joinCall(meetingId);
        }, 0);

        return () => window.clearTimeout(timer);
        // Runs once, on the visit the ring started.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const form = useForm({ body: '', image: null as File | null });

    /** The message being edited, and the text as it stands mid-edit. */
    const [editing, setEditing] = useState<number | null>(null);
    const [draft, setDraft] = useState('');
    const [emojiOpen, setEmojiOpen] = useState(false);

    /*
     * A clock, so the Edit link retires itself when the window closes rather
     * than sitting there until something else causes a re-render.
     */
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const tick = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(tick);
    }, []);

    /*
     * Which message the cursor has rested on long enough to show its reaction
     * picker. Held for a beat so the row of emoji does not flash up at every
     * mouse movement across the thread.
     */
    const [hovered, setHovered] = useState<number | null>(null);
    const hoverTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const startHover = (messageId: number) => {
        if (hoverTimer.current !== null) {
            clearTimeout(hoverTimer.current);
        }

        hoverTimer.current = setTimeout(() => setHovered(messageId), 2000);
    };

    const endHover = () => {
        if (hoverTimer.current !== null) {
            clearTimeout(hoverTimer.current);
            hoverTimer.current = null;
        }

        setHovered(null);
    };

    // Leaving the page mid-hover must not fire the timer into a gone component.
    useEffect(
        () => () => {
            if (hoverTimer.current !== null) {
                clearTimeout(hoverTimer.current);
            }
        },
        [],
    );

    /*
     * Land on the newest message, every time.
     *
     * The scroll box is driven directly rather than through
     * scrollIntoView(): that walks up every scrollable ancestor, so it used to
     * drag the whole page down as well as the thread. Setting scrollTop moves
     * one box and nothing else.
     */
    useEffect(() => {
        const box = scrollRef.current;

        if (box === null) {
            return;
        }

        box.scrollTop = box.scrollHeight;
    }, [active?.messages.length, active?.id]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        /*
         * form.processing is the important half of this guard.
         *
         * The box is only cleared once the server answers, so during that
         * round trip the text is still sitting there — and Enter reaches this
         * function directly from the textarea's onKeyDown, going around the
         * Send button that is already disabled. Pressing Enter twice sent the
         * same message twice, three times sent it three times.
         */
        if (form.processing) {
            return;
        }

        // A picture on its own is a message, so an empty box is only empty
        // when nothing is attached either.
        if (
            active === null ||
            (form.data.body.trim() === '' && form.data.image === null)
        ) {
            return;
        }

        form.post(
            sendMessage.url({
                current_team: team.slug,
                conversation: active.id,
            }),
            {
                preserveScroll: true,
                forceFormData: true,
                /*
                 * Only the thread and the list change when a message lands.
                 * Without this the whole page's props are rebuilt and sent
                 * back on every send, which is most of the pause between
                 * pressing Enter and seeing the line appear.
                 */
                only: ['threads', 'active'],
                onSuccess: () => {
                    form.reset('body', 'image');
                    setEmojiOpen(false);
                },
            },
        );
    };

    const saveEdit = (messageId: number) => {
        if (active === null || draft.trim() === '') {
            return;
        }

        router.patch(
            editMessage.url({
                current_team: team.slug,
                conversation: active.id,
                message: messageId,
            }),
            { body: draft },
            {
                ...IN_PLACE,
                onSuccess: () => setEditing(null),
            },
        );
    };

    const removeMessage = (messageId: number) => {
        if (active === null) {
            return;
        }

        /*
         * The server answers a removal with a "Message removed." toast; this
         * only has to cover the case where it could not be done.
         */
        router.delete(
            removeMessageRoute.url({
                current_team: team.slug,
                conversation: active.id,
                message: messageId,
            }),
            {
                ...IN_PLACE,
                onError: () =>
                    toast.error('That message could not be removed.'),
            },
        );
    };

    /*
     * Group chat membership. Only the team's creator is offered these, and
     * the server checks it again. The toast saying who was added or removed
     * comes back with the response.
     */
    const GROUP_IN_PLACE = {
        ...IN_PLACE,
        only: ['threads', 'active', 'group'],
        onStart: () => setGroupBusy(true),
        onFinish: () => setGroupBusy(false),
        onError: (errors: Record<string, string>) =>
            toast.error(
                Object.values(errors)[0] ?? 'That could not be changed.',
            ),
    };

    const inviteMember = (memberId: number) => {
        if (active === null || groupBusy) {
            return;
        }

        router.post(
            inviteToChat.url({
                current_team: team.slug,
                conversation: active.id,
            }),
            { user_id: memberId },
            GROUP_IN_PLACE,
        );
    };

    const removeMember = (memberId: number) => {
        if (active === null || groupBusy) {
            return;
        }

        router.delete(
            removeFromChat.url({
                current_team: team.slug,
                conversation: active.id,
                member: memberId,
            }),
            GROUP_IN_PLACE,
        );
    };

    /*
     * The chip changes the moment it is pressed; the request only confirms it.
     * Inertia puts the old reactions back by itself if the server refuses.
     *
     * The other side does not wait on this either: the controller broadcasts
     * the change on the thread's channel, and ThreadChannel re-reads the
     * thread when it hears it — the same path a new message takes.
     */
    const react = (messageId: number, emoji: string) => {
        if (active === null) {
            return;
        }

        router
            .optimistic<Props>((props) =>
                props.active === null
                    ? {}
                    : {
                          active: {
                              ...props.active,
                              messages: props.active.messages.map((message) =>
                                  message.id === messageId
                                      ? {
                                            ...message,
                                            reactions: toggleReaction(
                                                message.reactions,
                                                emoji,
                                            ),
                                        }
                                      : message,
                              ),
                          },
                      },
            )
            .post(
                reactToMessage.url({
                    current_team: team.slug,
                    conversation: active.id,
                    message: messageId,
                }),
                { emoji },
                { ...IN_PLACE, only: ['active'] },
            );
    };

    const needle = find.trim().toLowerCase();
    const visible =
        needle === ''
            ? threads
            : threads.filter(
                  (thread) =>
                      thread.title.toLowerCase().includes(needle) ||
                      thread.subtitle.toLowerCase().includes(needle),
              );

    return (
        <>
            <Head title="Messages" />

            {/*
             * Keyed on the thread so switching threads leaves the old channel
             * and joins the new one, rather than listening to both.
             */}
            {active !== null && (
                <ThreadChannel key={active.id} conversationId={active.id} />
            )}

            {call !== null && (
                <VideoCall
                    credentials={call.credentials}
                    people={call.people}
                    onLeave={leaveCall}
                    title={active?.project ?? 'Call'}
                    participant={active?.title ?? 'The other side'}
                />
            )}

            <div
                style={{
                    maxWidth: 'clamp(1320px, 100vw - 320px, 1600px)',
                    margin: '0 auto',
                    padding: '24px clamp(16px, 4vw, 32px) 24px',
                    /*
                     * The inbox owns the viewport rather than growing with the
                     * thread. Everything that scrolls does so inside its own
                     * box, so the thread list stays put while you read back
                     * through a conversation — the way a chat app behaves.
                     *
                     * 100dvh, not vh: on a phone the browser chrome comes and
                     * goes, and vh measures the tallest case, which pushes the
                     * composer under the address bar.
                     */
                    height: 'calc(100dvh - 64px)',
                    display: 'flex',
                    flexDirection: 'column',
                    minHeight: 0,
                }}
            >
                <div style={{ marginBottom: 14, flex: 'none' }}>
                    <h3 style={{ margin: 0 }}>Messages</h3>
                    <div style={{ fontSize: 13, color: MUTED(68) }}>
                        One conversation for each project you&rsquo;re working
                        on
                    </div>
                </div>

                {threads.length === 0 ? (
                    <EmptyInbox />
                ) : (
                    <div
                        className="msg-grid"
                        style={{
                            gap: 16,
                            /*
                             * Fills the space the header leaves. minHeight: 0
                             * is what actually lets the children scroll: a grid
                             * item defaults to min-height auto, which refuses to
                             * shrink below its content and pushes the overflow
                             * onto the page instead of into the panel.
                             */
                            flex: 1,
                            minHeight: 0,
                            alignItems: 'stretch',
                        }}
                    >
                        <Panel
                            padding="none"
                            gap="none"
                            style={{ overflowY: 'auto', minHeight: 0 }}
                        >
                            <div
                                style={{
                                    padding: '13px 14px 11px',
                                    borderBottom:
                                        '1px solid var(--color-divider)',
                                    position: 'sticky',
                                    top: 0,
                                    zIndex: 1,
                                    background: 'var(--color-surface)',
                                }}
                            >
                                <span
                                    style={{
                                        display: 'block',
                                        fontSize: 10,
                                        letterSpacing: '.12em',
                                        textTransform: 'uppercase',
                                        color: MUTED(50),
                                        marginBottom: 8,
                                    }}
                                >
                                    Conversations
                                </span>

                                <Input
                                    type="search"
                                    value={find}
                                    onChange={(event) =>
                                        setFind(event.target.value)
                                    }
                                    placeholder="Find a chat…"
                                    aria-label="Find a chat"
                                    style={{ fontSize: 13 }}
                                />
                            </div>

                            {visible.length === 0 && (
                                <div
                                    style={{
                                        padding: '18px 16px',
                                        fontSize: 12.5,
                                        color: MUTED(60),
                                    }}
                                >
                                    Nothing matches “{find}”.
                                </div>
                            )}

                            {visible.map((thread) => (
                                <button
                                    key={thread.id}
                                    type="button"
                                    onClick={() =>
                                        router.get(
                                            showThread.url({
                                                current_team: team.slug,
                                                conversation: thread.id,
                                            }),
                                            {},
                                            { preserveState: false },
                                        )
                                    }
                                    style={{
                                        display: 'block',
                                        width: '100%',
                                        textAlign: 'left',
                                        border: 0,
                                        borderBottom:
                                            '1px solid var(--color-divider)',
                                        padding: '12px 14px',
                                        font: 'inherit',
                                        cursor: 'pointer',
                                        color: 'var(--color-text)',
                                        background: thread.isActive
                                            ? 'color-mix(in srgb, var(--color-accent) 12%, transparent)'
                                            : 'transparent',
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'baseline',
                                            gap: 8,
                                        }}
                                    >
                                        <span
                                            style={{
                                                fontSize: 13.5,
                                                marginRight: 'auto',
                                                fontWeight: thread.isUnread
                                                    ? 600
                                                    : 400,
                                            }}
                                        >
                                            {thread.title}
                                        </span>
                                        {thread.isUnread && (
                                            <span
                                                aria-label="Unread"
                                                style={{
                                                    width: 7,
                                                    height: 7,
                                                    borderRadius: '50%',
                                                    background:
                                                        'var(--color-accent)',
                                                }}
                                            />
                                        )}
                                        {thread.at && (
                                            <span
                                                style={{
                                                    fontSize: 10.5,
                                                    color: MUTED(60),
                                                }}
                                            >
                                                {thread.at}
                                            </span>
                                        )}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 11,
                                            color: MUTED(65),
                                        }}
                                    >
                                        {thread.subtitle}
                                    </div>
                                    {thread.preview && (
                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                color: MUTED(55),
                                                marginTop: 2,
                                                overflow: 'hidden',
                                                textOverflow: 'ellipsis',
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {thread.preview}
                                        </div>
                                    )}
                                </button>
                            ))}

                            {/* The foot of the list, under the last thread. */}
                            {active !== null && (
                                <GroupChatPanel
                                    key={active.id}
                                    group={group}
                                    busy={groupBusy}
                                    onInvite={inviteMember}
                                    onRemove={removeMember}
                                />
                            )}
                        </Panel>

                        {active !== null && (
                            <Panel
                                padding="none"
                                gap="none"
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    minHeight: 0,
                                }}
                            >
                                <div
                                    style={{
                                        padding: '13px 18px',
                                        borderBottom:
                                            '1px solid var(--color-divider)',
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 12,
                                    }}
                                >
                                    <div style={{ minWidth: 0, flex: 1 }}>
                                        <div style={{ fontSize: 14 }}>
                                            {active.title}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                color: MUTED(65),
                                            }}
                                        >
                                            {active.project}
                                        </div>
                                    </div>

                                    {/* Absent, not disabled, when the platform
                                        has no Agora credentials to call with. */}
                                    {/* Joins the running call rather than
                                        opening a second one beside it. */}
                                    {videoEnabled && (
                                        <Btn
                                            variant="secondary"
                                            onClick={startCall}
                                            disabled={callBusy}
                                            title={
                                                active.call
                                                    ? 'Join the call running on this thread'
                                                    : 'Start a video call on this thread'
                                            }
                                        >
                                            <VideoCameraIcon />
                                            {active.call ? 'Join call' : 'Call'}
                                        </Btn>
                                    )}

                                    {videoEnabled && (
                                        <Btn
                                            variant="ghost"
                                            onClick={() =>
                                                setScheduling(!scheduling)
                                            }
                                            title="Invite them to a call later"
                                        >
                                            <CalendarPlusIcon />
                                            Schedule
                                        </Btn>
                                    )}
                                </div>

                                {scheduling && (
                                    <div
                                        style={{
                                            display: 'flex',
                                            flexWrap: 'wrap',
                                            alignItems: 'center',
                                            gap: 10,
                                            padding: '10px 18px',
                                            borderBottom:
                                                '1px solid var(--color-divider)',
                                        }}
                                    >
                                        <label
                                            htmlFor="meeting-at"
                                            style={{ fontSize: 12.5 }}
                                        >
                                            Meet at
                                        </label>
                                        <input
                                            id="meeting-at"
                                            type="datetime-local"
                                            className="input"
                                            {...meetingWindow()}
                                            value={scheduledAt}
                                            onChange={(event) =>
                                                setScheduledAt(
                                                    event.target.value,
                                                )
                                            }
                                            style={{
                                                width: 'auto',
                                                flex: 1,
                                                minWidth: 190,
                                            }}
                                        />
                                        <Btn
                                            variant="secondary"
                                            onClick={scheduleCall}
                                            disabled={
                                                callBusy || scheduledAt === ''
                                            }
                                        >
                                            Send invitation
                                        </Btn>
                                        <Btn
                                            variant="ghost"
                                            onClick={() => setScheduling(false)}
                                        >
                                            Cancel
                                        </Btn>
                                    </div>
                                )}

                                {/* Calls booked for later, soonest first.
                                    Joinable at any time: turning up early
                                    is not a thing worth preventing. */}
                                {active.meetings.length > 0 && (
                                    <div
                                        style={{
                                            borderBottom:
                                                '1px solid var(--color-divider)',
                                        }}
                                    >
                                        {active.meetings.map((meeting) => (
                                            <div
                                                key={meeting.id}
                                                style={{
                                                    display: 'flex',
                                                    flexWrap: 'wrap',
                                                    alignItems: 'center',
                                                    gap: 10,
                                                    padding: '9px 18px',
                                                    fontSize: 12.5,
                                                }}
                                            >
                                                <CalendarPlusIcon
                                                    style={{
                                                        color: 'var(--color-accent)',
                                                    }}
                                                />
                                                <span
                                                    style={{
                                                        marginRight: 'auto',
                                                    }}
                                                >
                                                    {meeting.isMine
                                                        ? 'You invited them to a call '
                                                        : 'They invited you to a call '}
                                                    {meeting.scheduledFor}
                                                </span>
                                                <Btn
                                                    variant="secondary"
                                                    onClick={() =>
                                                        joinCall(meeting.id)
                                                    }
                                                    disabled={callBusy}
                                                >
                                                    Join
                                                </Btn>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {/* A call somebody is in on this thread. Shown
                                    to anyone not in it, however long ago it
                                    started — the ring only lasts 45 seconds.
                                    It carries no token; joining asks the
                                    server for one of our own. */}
                                {runningCall !== null && call === null && (
                                    <div
                                        style={{
                                            display: 'flex',
                                            flexWrap: 'wrap',
                                            alignItems: 'center',
                                            gap: 12,
                                            padding: '10px 18px',
                                            background:
                                                'var(--color-accent-800)',
                                            color: 'var(--color-accent-100)',
                                            fontSize: 13,
                                        }}
                                    >
                                        <span
                                            style={{
                                                marginRight: 'auto',
                                                minWidth: 0,
                                            }}
                                        >
                                            A video call is running on this
                                            thread
                                            {runningCall.people.length > 0 &&
                                                ` with ${runningCall.people.join(', ')}`}
                                            .
                                        </span>
                                        <Btn
                                            variant="secondary"
                                            onClick={() =>
                                                joinCall(runningCall.id)
                                            }
                                            disabled={callBusy}
                                        >
                                            Join
                                        </Btn>
                                        <Btn
                                            variant="ghost"
                                            onClick={() =>
                                                setDismissedCall(runningCall.id)
                                            }
                                        >
                                            Dismiss
                                        </Btn>
                                    </div>
                                )}

                                <div
                                    ref={scrollRef}
                                    style={{
                                        flex: 1,
                                        minHeight: 0,
                                        overflowY: 'auto',
                                        overflowAnchor: 'none',
                                        padding: 18,
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 10,
                                    }}
                                >
                                    {active.messages.length === 0 && (
                                        <span
                                            style={{
                                                fontSize: 12.5,
                                                color: MUTED(55),
                                                margin: 'auto',
                                            }}
                                        >
                                            No messages yet. Say hello.
                                        </span>
                                    )}

                                    {active.messages.map((message) => (
                                        <div
                                            key={message.id}
                                            onMouseEnter={() =>
                                                startHover(message.id)
                                            }
                                            onMouseLeave={endHover}
                                            style={{
                                                alignSelf: message.isMine
                                                    ? 'flex-end'
                                                    : 'flex-start',
                                                maxWidth: '68%',
                                            }}
                                        >
                                            <div
                                                style={{
                                                    /*
                                                     * Hug the content. Without
                                                     * this the bubble is a
                                                     * block and stretches to
                                                     * whatever the widest row
                                                     * below it is — the meta
                                                     * line "You · 11s ago Edit
                                                     * Remove" — so a one
                                                     * character message drew a
                                                     * bubble wide enough for a
                                                     * sentence.
                                                     */
                                                    width: 'fit-content',
                                                    maxWidth: '100%',
                                                    marginLeft: message.isMine
                                                        ? 'auto'
                                                        : undefined,
                                                    padding: isEmojiOnly(
                                                        message,
                                                    )
                                                        ? 0
                                                        : '9px 12px',
                                                    borderRadius:
                                                        'var(--radius-md)',
                                                    // A message that is only
                                                    // emoji is read, not
                                                    // parsed, so it gets the
                                                    // room to be seen.
                                                    fontSize: isEmojiOnly(
                                                        message,
                                                    )
                                                        ? 34
                                                        : 13,
                                                    lineHeight: isEmojiOnly(
                                                        message,
                                                    )
                                                        ? 1.15
                                                        : 1.5,
                                                    whiteSpace: 'pre-wrap',
                                                    wordBreak: 'break-word',
                                                    fontStyle: message.isRemoved
                                                        ? 'italic'
                                                        : undefined,
                                                    color: message.isRemoved
                                                        ? MUTED(50)
                                                        : undefined,
                                                    background:
                                                        message.isRemoved ||
                                                        isEmojiOnly(message)
                                                            ? 'transparent'
                                                            : message.isMine
                                                              ? 'color-mix(in srgb, var(--color-accent) 16%, transparent)'
                                                              : 'color-mix(in srgb, var(--color-text) 6%, transparent)',
                                                    border: message.isRemoved
                                                        ? `1px dashed ${MUTED(20)}`
                                                        : undefined,
                                                }}
                                            >
                                                {message.isRemoved ? (
                                                    'Message removed'
                                                ) : editing === message.id ? (
                                                    <div
                                                        style={{
                                                            display: 'flex',
                                                            flexDirection:
                                                                'column',
                                                            gap: 6,
                                                        }}
                                                    >
                                                        <textarea
                                                            value={draft}
                                                            rows={2}
                                                            autoFocus
                                                            onChange={(e) =>
                                                                setDraft(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            style={{
                                                                fontSize: 13,
                                                                padding: 6,
                                                                borderRadius: 6,
                                                                border: `1px solid ${MUTED(20)}`,
                                                                background:
                                                                    'var(--color-surface, #fff)',
                                                            }}
                                                        />
                                                        <div
                                                            style={{
                                                                display: 'flex',
                                                                gap: 6,
                                                            }}
                                                        >
                                                            <Btn
                                                                style={{
                                                                    fontSize: 11.5,
                                                                    padding:
                                                                        '3px 9px',
                                                                }}
                                                                onClick={() =>
                                                                    saveEdit(
                                                                        message.id,
                                                                    )
                                                                }
                                                            >
                                                                Save
                                                            </Btn>
                                                            <Btn
                                                                variant="ghost"
                                                                style={{
                                                                    fontSize: 11.5,
                                                                    padding:
                                                                        '3px 9px',
                                                                }}
                                                                onClick={() =>
                                                                    setEditing(
                                                                        null,
                                                                    )
                                                                }
                                                            >
                                                                Cancel
                                                            </Btn>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <>
                                                        {message.imageUrl && (
                                                            <img
                                                                src={
                                                                    message.imageUrl
                                                                }
                                                                alt=""
                                                                style={{
                                                                    maxWidth:
                                                                        '100%',
                                                                    borderRadius: 8,
                                                                    marginBottom:
                                                                        message.body
                                                                            ? 6
                                                                            : 0,
                                                                    display:
                                                                        'block',
                                                                }}
                                                            />
                                                        )}
                                                        {message.body}
                                                    </>
                                                )}
                                            </div>

                                            {/*
                                             * Reactions already left, plus the
                                             * picker while hovered. Rendered
                                             * only when there is something to
                                             * show, so an untouched message
                                             * carries no empty strip.
                                             */}
                                            {!message.isRemoved &&
                                                (message.reactions.length > 0 ||
                                                    hovered === message.id) && (
                                                    <div
                                                        style={{
                                                            display: 'flex',
                                                            flexWrap: 'wrap',
                                                            gap: 4,
                                                            marginTop: 4,
                                                            justifyContent:
                                                                message.isMine
                                                                    ? 'flex-end'
                                                                    : 'flex-start',
                                                        }}
                                                    >
                                                        {message.reactions.map(
                                                            (reaction) => (
                                                                <button
                                                                    key={
                                                                        reaction.emoji
                                                                    }
                                                                    type="button"
                                                                    onClick={() =>
                                                                        react(
                                                                            message.id,
                                                                            reaction.emoji,
                                                                        )
                                                                    }
                                                                    title="Toggle this reaction"
                                                                    style={{
                                                                        fontSize: 11.5,
                                                                        padding:
                                                                            '1px 7px',
                                                                        borderRadius: 999,
                                                                        border: `1px solid ${reaction.reacted ? 'var(--color-primary, #4a7c4e)' : MUTED(18)}`,
                                                                        background:
                                                                            reaction.reacted
                                                                                ? 'color-mix(in srgb, var(--color-accent) 18%, transparent)'
                                                                                : 'transparent',
                                                                    }}
                                                                >
                                                                    {
                                                                        reaction.emoji
                                                                    }{' '}
                                                                    {
                                                                        reaction.count
                                                                    }
                                                                </button>
                                                            ),
                                                        )}

                                                        {/*
                                                         * Appears once the cursor
                                                         * has rested on the
                                                         * message for two seconds,
                                                         * so it does not flash up
                                                         * at every pass of the
                                                         * mouse across the thread.
                                                         */}
                                                        {hovered ===
                                                            message.id && (
                                                            <div
                                                                style={{
                                                                    display:
                                                                        'flex',
                                                                    gap: 2,
                                                                    padding: 3,
                                                                    borderRadius: 999,
                                                                    border: `1px solid ${MUTED(15)}`,
                                                                    background:
                                                                        'var(--color-surface, #fff)',
                                                                }}
                                                            >
                                                                {active.reactionChoices.map(
                                                                    (emoji) => (
                                                                        <button
                                                                            key={
                                                                                emoji
                                                                            }
                                                                            type="button"
                                                                            title="React"
                                                                            onClick={() =>
                                                                                react(
                                                                                    message.id,
                                                                                    emoji,
                                                                                )
                                                                            }
                                                                            style={{
                                                                                fontSize: 14,
                                                                                padding:
                                                                                    '1px 3px',
                                                                                lineHeight: 1,
                                                                            }}
                                                                        >
                                                                            {
                                                                                emoji
                                                                            }
                                                                        </button>
                                                                    ),
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>
                                                )}

                                            <div
                                                style={{
                                                    fontSize: 10.5,
                                                    color: MUTED(55),
                                                    marginTop: 3,
                                                    display: 'flex',
                                                    gap: 6,
                                                    justifyContent:
                                                        message.isMine
                                                            ? 'flex-end'
                                                            : 'flex-start',
                                                }}
                                            >
                                                <span>
                                                    {message.isMine
                                                        ? 'You'
                                                        : message.author}
                                                    {message.at
                                                        ? ` · ${message.at}`
                                                        : ''}
                                                    {message.isEdited
                                                        ? ' · edited'
                                                        : ''}
                                                </span>

                                                {/* Only the sender's own, and
                                                    not once it is removed. */}
                                                {message.isMine &&
                                                    !message.isRemoved &&
                                                    editing !== message.id &&
                                                    confirmingRemoval ===
                                                        message.id && (
                                                        <>
                                                            {/*
                                                             * Asked first: a
                                                             * removed message
                                                             * cannot be brought
                                                             * back.
                                                             */}
                                                            <span>
                                                                Remove this
                                                                message?
                                                            </span>
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    setConfirmingRemoval(
                                                                        null,
                                                                    );
                                                                    removeMessage(
                                                                        message.id,
                                                                    );
                                                                }}
                                                                style={{
                                                                    color: 'var(--color-text)',
                                                                    textDecoration:
                                                                        'underline',
                                                                }}
                                                            >
                                                                Yes, remove
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    setConfirmingRemoval(
                                                                        null,
                                                                    )
                                                                }
                                                                style={{
                                                                    color: MUTED(
                                                                        60,
                                                                    ),
                                                                    textDecoration:
                                                                        'underline',
                                                                }}
                                                            >
                                                                Cancel
                                                            </button>
                                                        </>
                                                    )}

                                                {message.isMine &&
                                                    !message.isRemoved &&
                                                    editing !== message.id &&
                                                    confirmingRemoval !==
                                                        message.id && (
                                                        <>
                                                            {/*
                                                             * Editing closes
                                                             * 30 seconds after
                                                             * sending. The
                                                             * clock ticking
                                                             * above is what
                                                             * makes this
                                                             * disappear on its
                                                             * own; the server
                                                             * refuses it
                                                             * either way.
                                                             */}
                                                            {message.editableUntil !==
                                                                null &&
                                                                now <
                                                                    message.editableUntil && (
                                                                    <button
                                                                        type="button"
                                                                        title={`Editable for ${Math.max(0, Math.ceil((message.editableUntil - now) / 1000))}s more`}
                                                                        onClick={() => {
                                                                            setEditing(
                                                                                message.id,
                                                                            );
                                                                            setDraft(
                                                                                message.body ??
                                                                                    '',
                                                                            );
                                                                        }}
                                                                        style={{
                                                                            color: MUTED(
                                                                                60,
                                                                            ),
                                                                            textDecoration:
                                                                                'underline',
                                                                        }}
                                                                    >
                                                                        Edit
                                                                    </button>
                                                                )}
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    setConfirmingRemoval(
                                                                        message.id,
                                                                    )
                                                                }
                                                                style={{
                                                                    color: MUTED(
                                                                        60,
                                                                    ),
                                                                    textDecoration:
                                                                        'underline',
                                                                }}
                                                            >
                                                                Remove
                                                            </button>
                                                        </>
                                                    )}
                                            </div>
                                        </div>
                                    ))}

                                    <div ref={endRef} />
                                </div>

                                <form
                                    onSubmit={submit}
                                    style={{
                                        display: 'flex',
                                        gap: 8,
                                        padding: 14,
                                        borderTop:
                                            '1px solid var(--color-divider)',
                                    }}
                                >
                                    <textarea
                                        className="input"
                                        rows={1}
                                        maxLength={4000}
                                        placeholder="Write a message"
                                        value={form.data.body}
                                        onChange={(e) =>
                                            form.setData('body', e.target.value)
                                        }
                                        onKeyDown={(e) => {
                                            if (
                                                e.key === 'Enter' &&
                                                !e.shiftKey
                                            ) {
                                                submit(e);
                                            }
                                        }}
                                        style={{
                                            flex: 1,
                                            resize: 'none',
                                            minHeight: 38,
                                        }}
                                    />
                                    {/* Emoji are characters in the body, so
                                        this needs nothing from the server. */}
                                    <Btn
                                        variant="ghost"
                                        type="button"
                                        title="Emoji"
                                        onClick={() =>
                                            setEmojiOpen((open) => !open)
                                        }
                                    >
                                        <SmileyIcon />
                                    </Btn>

                                    <Btn
                                        variant="ghost"
                                        type="button"
                                        title="Attach a picture"
                                        onClick={() =>
                                            imageRef.current?.click()
                                        }
                                    >
                                        <ImageIcon />
                                    </Btn>
                                    <input
                                        ref={imageRef}
                                        type="file"
                                        accept="image/*"
                                        hidden
                                        onChange={(e) =>
                                            form.setData(
                                                'image',
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                    />

                                    <Btn
                                        variant="primary"
                                        type="submit"
                                        disabled={
                                            form.processing ||
                                            (form.data.body.trim() === '' &&
                                                form.data.image === null)
                                        }
                                    >
                                        <PaperPlaneRightIcon />
                                        Send
                                    </Btn>
                                </form>

                                {/* Sits under the composer so neither the
                                    picker nor the attachment note covers the
                                    last message in the thread. */}
                                {(emojiOpen || form.data.image !== null) && (
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            flexWrap: 'wrap',
                                            gap: 6,
                                            padding: '0 14px 12px',
                                        }}
                                    >
                                        {emojiOpen &&
                                            QUICK_EMOJI.map((emoji) => (
                                                <button
                                                    key={emoji}
                                                    type="button"
                                                    onClick={() =>
                                                        form.setData(
                                                            'body',
                                                            form.data.body +
                                                                emoji,
                                                        )
                                                    }
                                                    style={{ fontSize: 18 }}
                                                >
                                                    {emoji}
                                                </button>
                                            ))}

                                        {form.data.image !== null && (
                                            <span
                                                style={{
                                                    fontSize: 11.5,
                                                    color: MUTED(60),
                                                    display: 'flex',
                                                    gap: 6,
                                                    alignItems: 'center',
                                                }}
                                            >
                                                {form.data.image.name}
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        form.setData(
                                                            'image',
                                                            null,
                                                        );

                                                        if (imageRef.current) {
                                                            imageRef.current.value =
                                                                '';
                                                        }
                                                    }}
                                                    style={{
                                                        textDecoration:
                                                            'underline',
                                                    }}
                                                >
                                                    remove
                                                </button>
                                            </span>
                                        )}
                                    </div>
                                )}
                            </Panel>
                        )}

                        {active !== null && (
                            <Panel
                                padding="lg"
                                gap="lg"
                                style={{ overflowY: 'auto', minHeight: 0 }}
                            >
                                <PanelKicker>Project</PanelKicker>

                                <div>
                                    <div style={{ fontSize: 13.5 }}>
                                        {active.project}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 12,
                                            color: MUTED(60),
                                        }}
                                    >
                                        with {active.title}
                                    </div>
                                </div>

                                {active.intel === null ? (
                                    <p
                                        style={{
                                            margin: 0,
                                            fontSize: 12.5,
                                            lineHeight: 1.6,
                                            color: MUTED(62),
                                        }}
                                    >
                                        Milestones appear here once an agreement
                                        has been drafted for this project.
                                    </p>
                                ) : (
                                    <>
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'baseline',
                                                gap: 8,
                                            }}
                                        >
                                            <span style={{ fontSize: 12.5 }}>
                                                {active.intel.title ??
                                                    'All milestones approved'}
                                            </span>
                                            <span
                                                style={{
                                                    marginLeft: 'auto',
                                                    fontSize: 12.5,
                                                    color: 'var(--color-accent)',
                                                    fontVariantNumeric:
                                                        'tabular-nums',
                                                }}
                                            >
                                                {active.intel.progress}%
                                            </span>
                                        </div>

                                        {/* The figure counts approvals only —
                                            see Agreement::progress(). */}
                                        <div
                                            role="progressbar"
                                            aria-valuenow={
                                                active.intel.progress
                                            }
                                            aria-valuemin={0}
                                            aria-valuemax={100}
                                            aria-label="Milestones approved"
                                            style={{
                                                height: 4,
                                                borderRadius: 999,
                                                background: MUTED(12),
                                                overflow: 'hidden',
                                            }}
                                        >
                                            <span
                                                style={{
                                                    display: 'block',
                                                    height: '100%',
                                                    width: `${active.intel.progress}%`,
                                                    background:
                                                        'var(--color-accent)',
                                                }}
                                            />
                                        </div>

                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                color: MUTED(58),
                                            }}
                                        >
                                            {active.intel.due !== null && (
                                                <>Due {active.intel.due} · </>
                                            )}
                                            {active.intel.open}{' '}
                                            {active.intel.open === 1
                                                ? 'milestone'
                                                : 'milestones'}{' '}
                                            open
                                        </div>

                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                color: MUTED(50),
                                            }}
                                        >
                                            Contract {active.intel.reference}
                                        </div>
                                    </>
                                )}
                            </Panel>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

/**
 * Who is in the open thread's group chat.
 *
 * Being on the student's team is not enough to read a thread: the team's
 * creator invites teammates one by one, and can take them out again. Everyone
 * else sees the list. The thread's student and the creator have no Remove.
 */
function GroupChatPanel({
    group,
    busy,
    onInvite,
    onRemove,
}: {
    group: GroupState;
    busy: boolean;
    onInvite: (memberId: number) => void;
    onRemove: (memberId: number) => void;
}) {
    /* The person whose Remove is waiting for a yes. */
    const [confirming, setConfirming] = useState<number | null>(null);

    const note: React.CSSProperties = {
        fontSize: 11.5,
        color: MUTED(60),
        lineHeight: 1.45,
    };

    if (group.teamName === null) {
        if (group.needsTeam) {
            return (
                <div style={{ padding: '12px 14px', ...note }}>
                    <UsersThreeIcon size={14} /> You need a team before you can
                    start a group chat. Create one from Team in the header, then
                    invite teammates here.
                </div>
            );
        }

        return null;
    }

    const creator = group.people.find((person) => person.isCreator);

    return (
        <div
            data-test="group-chat-panel"
            style={{
                padding: '12px 14px',
                display: 'grid',
                gap: 8,
                borderTop: '1px solid var(--color-divider)',
            }}
        >
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 6,
                    fontSize: 12.5,
                }}
            >
                <UsersThreeIcon size={15} />
                Group chat · {group.teamName}
            </div>

            <div style={note}>
                {group.canManage
                    ? 'You created this team, so you choose which teammates join this chat with the client.'
                    : `${creator?.name ?? 'The team creator'} chooses which teammates join this chat.`}
            </div>

            <ul
                style={{
                    listStyle: 'none',
                    margin: 0,
                    padding: 0,
                    display: 'grid',
                    gap: 4,
                }}
            >
                {group.people.map((person) => {
                    const removable =
                        group.canManage &&
                        !person.isCreator &&
                        !person.isThreadOwner;

                    return (
                        <li
                            key={person.id}
                            style={{
                                display: 'flex',
                                flexWrap: 'wrap',
                                alignItems: 'center',
                                gap: 6,
                                fontSize: 12.5,
                            }}
                        >
                            <span style={{ marginRight: 'auto', minWidth: 0 }}>
                                {person.name}
                                {person.isCreator && (
                                    <span style={note}> · team creator</span>
                                )}
                                {!person.isCreator && person.isThreadOwner && (
                                    <span style={note}>
                                        {' '}
                                        · started the chat
                                    </span>
                                )}
                            </span>

                            {removable && confirming !== person.id && (
                                <button
                                    type="button"
                                    disabled={busy}
                                    onClick={() => setConfirming(person.id)}
                                    style={{
                                        ...note,
                                        textDecoration: 'underline',
                                    }}
                                >
                                    Remove
                                </button>
                            )}

                            {removable && confirming === person.id && (
                                <span
                                    style={{
                                        display: 'inline-flex',
                                        gap: 6,
                                        ...note,
                                    }}
                                >
                                    Remove from chat?
                                    <button
                                        type="button"
                                        disabled={busy}
                                        onClick={() => {
                                            setConfirming(null);
                                            onRemove(person.id);
                                        }}
                                        style={{
                                            color: 'var(--color-text)',
                                            textDecoration: 'underline',
                                        }}
                                    >
                                        Yes
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setConfirming(null)}
                                        style={{ textDecoration: 'underline' }}
                                    >
                                        Cancel
                                    </button>
                                </span>
                            )}
                        </li>
                    );
                })}
            </ul>

            {group.canManage &&
                (group.invitable.length > 0 ? (
                    <div style={{ display: 'grid', gap: 4 }}>
                        <div style={note}>Invite teammates</div>
                        {group.invitable.map((teammate) => (
                            <div
                                key={teammate.id}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 6,
                                    fontSize: 12.5,
                                }}
                            >
                                <span
                                    style={{ marginRight: 'auto', minWidth: 0 }}
                                >
                                    {teammate.name}
                                </span>
                                <Btn
                                    variant="secondary"
                                    disabled={busy}
                                    onClick={() => onInvite(teammate.id)}
                                    style={{ minHeight: 28, paddingInline: 10 }}
                                >
                                    Invite
                                </Btn>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div style={note}>
                        Everyone on {group.teamName} is in this chat.
                    </div>
                ))}
        </div>
    );
}

/**
 * The live half of an open thread: new messages, a call starting, a meeting
 * being scheduled.
 *
 * A component of its own so it can be left unmounted when no thread is open.
 * The three subscriptions used to sit in the page and named the channel
 * `conversations.${active?.id ?? 0}` — so an empty inbox, which is every new
 * account, subscribed to conversation 0. No such thread exists, the channel
 * authorisation refused it, and each page load raised three 403s from
 * /broadcasting/auth. useEcho has no "off" switch; it subscribes when it
 * mounts, so the only way not to subscribe is not to mount it.
 */
function ThreadChannel({ conversationId }: { conversationId: number }) {
    const channel = `conversations.${conversationId}`;

    useEcho(
        channel,
        '.message.sent',
        () => {
            router.reload({ only: ['threads', 'active'] });
        },
        [conversationId],
    );

    /*
     * The invitation rides the thread's own private channel and deliberately
     * carries no token. It only says to re-read the thread, which now names
     * the running call; joining asks the server for a token, where the
     * participant check runs again against the authenticated user.
     */
    useEcho(
        channel,
        '.meeting.started',
        () => {
            router.reload({ only: ['active'] });
        },
        [conversationId],
    );

    useEcho(
        channel,
        '.meeting.scheduled',
        () => {
            /* Nothing to join yet — it just belongs in the thread's list. */
            router.reload({ only: ['active'] });
        },
        [conversationId],
    );

    return null;
}
