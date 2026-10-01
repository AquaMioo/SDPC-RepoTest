import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import VideoCall from '@/components/messaging/video-call';
import type {
    MeetingCredentials,
    MeetingPerson,
} from '@/components/messaging/video-call';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { CALL_WINDOW_CLOSED } from '@/lib/call-window';
import {
    heartbeat as meetingHeartbeat,
    leave as leaveMeeting,
    token as meetingToken,
} from '@/routes/meetings';

type Props = {
    meetingId: number;
    title: string;
    participant: string;
    joinable: boolean;
};

/**
 * The call, in a browser window of its own.
 *
 * Messenger opens this window (lib/call-window) when a call is started,
 * joined or answered, so the chat stays usable in the original window. It is
 * the same call: VideoCall and the same token, heartbeat and leave endpoints
 * the in-page screen used.
 *
 * The window closes itself once its person has left the call — Leave, or
 * everybody else hanging up (VideoCall's hangUpWhenAlone) — or when the call
 * has ended for everyone (the heartbeat answers 410). Closing the window or
 * losing the connection says goodbye on pagehide, as before. A refresh joins
 * again rather than closing.
 */
export default function CallWindow({
    meetingId,
    title,
    participant,
    joinable,
}: Props) {
    const team = useCurrentTeam();
    const args = { current_team: team.slug, meeting: meetingId };

    const [call, setCall] = useState<{
        credentials: MeetingCredentials;
        people: MeetingPerson[];
    } | null>(null);
    const [notice, setNotice] = useState<string | null>(
        joinable ? null : 'This call has already ended.',
    );
    const closing = useRef(false);

    /* Tell Messenger, then close — or say so when the browser will not let us. */
    const closeWindow = (message: string) => {
        if (closing.current) {
            return;
        }

        closing.current = true;
        window.opener?.postMessage(CALL_WINDOW_CLOSED, window.location.origin);
        setNotice(message);
        window.setTimeout(() => window.close(), 600);
    };

    /* Join: a token for the channel, behind the participant check. */
    useEffect(() => {
        if (!joinable) {
            closeWindow('This call has already ended.');

            return;
        }

        let cancelled = false;

        void sendJson(meetingToken.url(args))
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                const body = await response.json();

                if (!cancelled) {
                    setCall({
                        credentials: body.token as MeetingCredentials,
                        people: body.people as MeetingPerson[],
                    });
                }
            })
            .catch(() => {
                if (!cancelled) {
                    closeWindow('This call has ended.');
                }
            });

        return () => {
            cancelled = true;
        };
        // Joins once for this window's meeting.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    /* Still here, every 20 seconds; a 410 means the call ended for everyone. */
    useEffect(() => {
        if (call === null) {
            return;
        }

        const beat = async () => {
            try {
                const response = await sendJson(meetingHeartbeat.url(args));

                if (response.status === 410) {
                    closeWindow('The call has ended.');
                }
            } catch {
                /* A dropped beat is what the presence window allows for. */
            }
        };

        const timer = window.setInterval(() => void beat(), 20_000);

        /* Closing or refreshing the window says goodbye even mid-call. */
        const onPageHide = () => {
            void sendJson(leaveMeeting.url(args), 'PATCH', true);
        };

        window.addEventListener('pagehide', onPageHide);

        return () => {
            window.clearInterval(timer);
            window.removeEventListener('pagehide', onPageHide);
        };
        // Re-armed only when the call is joined.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [call]);

    const leave = () => {
        void sendJson(leaveMeeting.url(args), 'PATCH', true).catch(
            () => undefined,
        );

        setCall(null);
        closeWindow('You left the call.');
    };

    return (
        <>
            <Head title={`Call · ${title}`} />

            {call !== null && notice === null ? (
                <VideoCall
                    credentials={call.credentials}
                    people={call.people}
                    onLeave={leave}
                    title={title}
                    participant={participant}
                    hangUpWhenAlone
                />
            ) : (
                <div
                    style={{
                        minHeight: '100vh',
                        display: 'grid',
                        placeItems: 'center',
                        padding: 24,
                        background: 'var(--color-bg)',
                        color: 'var(--color-text)',
                        textAlign: 'center',
                    }}
                >
                    <div style={{ display: 'grid', gap: 8 }}>
                        <div style={{ fontSize: 16 }}>
                            {notice ?? 'Joining the call…'}
                        </div>
                        {notice !== null && (
                            <div style={{ fontSize: 13, opacity: 0.65 }}>
                                You can close this window.
                            </div>
                        )}
                    </div>
                </div>
            )}
        </>
    );
}

/**
 * The meeting endpoints answer JSON, so they are fetched directly. Laravel
 * checks X-XSRF-TOKEN against the cookie it set.
 */
function sendJson(url: string, method = 'POST', keepalive = false) {
    const xsrf = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return fetch(url, {
        method,
        credentials: 'same-origin',
        /* Lets a leave sent while the window is closing still arrive. */
        keepalive,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(xsrf ?? ''),
        },
    });
}
