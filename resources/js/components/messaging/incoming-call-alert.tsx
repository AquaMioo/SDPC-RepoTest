import { router, usePage } from '@inertiajs/react';
import { useEchoNotification } from '@laravel/echo-react';
import { PhoneXIcon, VideoCameraIcon } from '@phosphor-icons/react';
import { useEffect, useState } from 'react';

import { Btn } from '@/components/sdpc/btn';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { startRingtone } from '@/lib/ringtone';
import { show as showThread } from '@/routes/messages';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/** How long a call rings before it counts as missed. */
const RING_FOR_MS = 45_000;

type SharedProps = {
    auth?: { user?: { id: number } | null };
};

/** App\Notifications\Messaging\IncomingCall, as broadcast. */
type IncomingCall = {
    meeting_id: number;
    conversation_id: number;
    caller_name: string;
    project_title: string;
    started_at: string | null;
};

/** App\Notifications\Messaging\CallEnded, as broadcast. */
type CallEnded = {
    meeting_id: number;
};

/**
 * The phone ringing, on every signed-in screen.
 *
 * Somebody starting a call in a conversation you are part of rings you here
 * wherever you are — not only on the Messages page with that thread open. It
 * rings (sound, vibration on a phone, a flashing tab title) until you answer
 * or decline, the caller hangs up, or 45 seconds pass. Answering opens the
 * thread and joins the call; the token is asked for there, behind the
 * thread's own participant check, never carried in the ring.
 *
 * Mounted once by the signed-in shell.
 */
export default function IncomingCallAlert() {
    const userId = usePage<SharedProps>().props.auth?.user?.id;

    if (!userId) {
        return null;
    }

    return <Ringer userId={userId} />;
}

function Ringer({ userId }: { userId: number }) {
    const team = useCurrentTeam();
    const [call, setCall] = useState<IncomingCall | null>(null);
    const channel = `App.Models.User.${userId}`;

    useEchoNotification<IncomingCall>(
        channel,
        (incoming) => {
            setCall(incoming);
            /* The call is also a row in the bell. */
            router.reload({
                only: ['unreadNotifications', 'recentNotifications'],
            });
        },
        'call.incoming',
        [userId],
    );

    useEchoNotification<CallEnded>(
        channel,
        (ended) =>
            setCall((current) =>
                current !== null && current.meeting_id === ended.meeting_id
                    ? null
                    : current,
            ),
        'call.ended',
        [userId],
    );

    useEffect(() => {
        if (call === null) {
            return;
        }

        const stopRinging = startRingtone();
        const giveUp = window.setTimeout(() => setCall(null), RING_FOR_MS);

        navigator.vibrate?.([400, 200, 400, 1600, 400, 200, 400]);

        const title = document.title;
        let flash = false;
        const titleTimer = window.setInterval(() => {
            flash = !flash;
            document.title = flash
                ? `📞 ${call.caller_name} is calling`
                : title;
        }, 1000);

        return () => {
            stopRinging();
            window.clearTimeout(giveUp);
            window.clearInterval(titleTimer);
            navigator.vibrate?.(0);
            document.title = title;
        };
    }, [call]);

    if (call === null) {
        return null;
    }

    const answer = () => {
        setCall(null);

        router.visit(
            showThread.url(
                {
                    current_team: team.slug,
                    conversation: call.conversation_id,
                },
                { query: { join: call.meeting_id } },
            ),
        );
    };

    return (
        <div
            role="alertdialog"
            aria-live="assertive"
            aria-label={`Incoming video call from ${call.caller_name}`}
            data-test="incoming-call"
            className="card elev-lg"
            style={{
                position: 'fixed',
                right: 'clamp(12px, 3vw, 24px)',
                bottom: 'clamp(12px, 3vw, 24px)',
                zIndex: 60,
                width: 'min(340px, calc(100vw - 24px))',
                padding: 16,
                gap: 12,
                display: 'grid',
                background: 'var(--color-bg)',
            }}
        >
            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                <span
                    className="animate-pulse"
                    style={{
                        width: 42,
                        height: 42,
                        borderRadius: '50%',
                        display: 'grid',
                        placeItems: 'center',
                        flex: 'none',
                        fontSize: 20,
                        color: 'var(--color-bg)',
                        background: 'var(--color-accent)',
                    }}
                >
                    <VideoCameraIcon weight="fill" />
                </span>
                <div style={{ minWidth: 0 }}>
                    <div style={{ fontSize: 11.5, color: MUTED(60) }}>
                        Incoming video call
                    </div>
                    <div
                        style={{
                            fontSize: 15,
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                            whiteSpace: 'nowrap',
                        }}
                    >
                        {call.caller_name}
                    </div>
                    <div
                        style={{
                            fontSize: 11.5,
                            color: MUTED(60),
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                            whiteSpace: 'nowrap',
                        }}
                    >
                        {call.project_title}
                    </div>
                </div>
            </div>

            <div style={{ display: 'flex', gap: 8 }}>
                <Btn
                    variant="primary"
                    style={{ flex: 1 }}
                    onClick={answer}
                    autoFocus
                >
                    <VideoCameraIcon />
                    Answer
                </Btn>
                <Btn
                    variant="ghost"
                    style={{ flex: 1 }}
                    onClick={() => setCall(null)}
                >
                    <PhoneXIcon />
                    Decline
                </Btn>
            </div>
        </div>
    );
}
