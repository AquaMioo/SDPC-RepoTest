import {
    MicrophoneIcon,
    MicrophoneSlashIcon,
    MonitorArrowUpIcon,
    PhoneDisconnectIcon,
    VideoCameraIcon,
    VideoCameraSlashIcon,
} from '@phosphor-icons/react';
import type {
    IAgoraRTCClient,
    IAgoraRTCRemoteUser,
    ICameraVideoTrack,
    ILocalVideoTrack,
    IMicrophoneAudioTrack,
} from 'agora-rtc-sdk-ng';
import { useCallback, useEffect, useRef, useState } from 'react';

import { Btn } from '@/components/sdpc/btn';

/** What the token endpoint hands back. */
export type MeetingCredentials = {
    appId: string;
    channel: string;
    uid: number;
    token: string;
    expiresIn: number;
};

type Stage = 'connecting' | 'live' | 'failed';

/**
 * Say what went wrong in terms of something the reader can act on.
 *
 * The SDK reports "AgoraRTCError PERMISSION_DENIED: NotAllowedError", which is
 * accurate and useless: it names a browser API to somebody who wants to know
 * which button to press. Nearly every failure here is one of four situations,
 * and all four have a fix the person in front of the screen can carry out.
 */
function explain(thrown: unknown): { message: string; hint: string | null } {
    const raw =
        thrown instanceof Error
            ? `${thrown.name} ${thrown.message}`
            : String(thrown);

    if (/PERMISSION_DENIED|NotAllowedError|Permission denied/i.test(raw)) {
        return {
            message: 'Your browser blocked the camera and microphone.',
            hint: 'Open the padlock beside the address bar, set Camera and Microphone to Allow, then reload the page. A browser only asks once — after that it remembers.',
        };
    }

    if (/NOT_READABLE|NotReadableError|TrackStartError/i.test(raw)) {
        return {
            message: 'Something else is already using your camera.',
            hint: 'Close any other call — Zoom, Meet, Teams, another tab of this page — and try again.',
        };
    }

    if (/DEVICE_NOT_FOUND|NotFoundError|DevicesNotFound/i.test(raw)) {
        return {
            message: 'No camera or microphone was found.',
            hint: 'Plug one in, or check it is not disabled in your system settings.',
        };
    }

    if (/INVALID_TOKEN|TOKEN_EXPIRED|DYNAMIC_KEY/i.test(raw)) {
        return {
            message: 'This call pass is no longer valid.',
            hint: 'Leave and start the call again to get a fresh one.',
        };
    }

    return { message: 'The call could not be started.', hint: raw };
}

/**
 * A short tone confirming a control did something.
 *
 * Synthesised rather than loaded from a file: two oscillator sweeps are a few
 * lines, need no asset to fetch mid-call, and cannot fail on a slow
 * connection. Rising for on, falling for off — the shape carries the meaning
 * even when the call itself is noisy.
 *
 * Deliberately quiet and short. This is feedback for the person pressing the
 * button, not an announcement to the room, and it plays locally: the tone is
 * never published to the channel, so nobody else hears your microphone
 * clicking on and off.
 */
function chime(direction: 'on' | 'off'): void {
    try {
        const Ctor =
            window.AudioContext ??
            (window as unknown as { webkitAudioContext?: typeof AudioContext })
                .webkitAudioContext;

        if (!Ctor) {
            return;
        }

        const ctx = new Ctor();

        const play = async () => {
            /*
             * A fresh context can arrive suspended under the browser's
             * autoplay policy even inside a click handler, and scheduling
             * against a suspended clock plays nothing at all — silently. This
             * is why the tone did not sound the first time.
             */
            if (ctx.state === 'suspended') {
                await ctx.resume();
            }

            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            const [from, to] = direction === 'on' ? [520, 790] : [660, 400];
            const now = ctx.currentTime;
            const length = direction === 'on' ? 0.1 : 0.14;

            osc.type = 'sine';
            osc.frequency.setValueAtTime(from, now);
            osc.frequency.exponentialRampToValueAtTime(to, now + length);

            /* Ramped rather than switched, so it does not click at either end. */
            gain.gain.setValueAtTime(0.0001, now);
            gain.gain.exponentialRampToValueAtTime(0.16, now + 0.015);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + length);

            osc.connect(gain).connect(ctx.destination);
            osc.start(now);
            osc.stop(now + length + 0.02);

            /* Browsers cap concurrent contexts, so release it once done. */
            osc.onended = () => void ctx.close();
        };

        void play().catch(() => void ctx.close());
    } catch {
        /* Audio is a courtesy; never let it interfere with the call. */
    }
}

/**
 * A video call on one project conversation.
 *
 * The SDK is imported dynamically rather than at module scope: it is around
 * 400KB and nobody browsing the board should pay for it. The import only
 * happens when somebody actually opens a call.
 *
 * The credentials come from the server already scoped to one channel and one
 * uid — nothing here chooses a channel, and there is deliberately no way to
 * join one by name.
 */
export default function VideoCall({
    credentials,
    onLeave,
    title,
    participant,
}: {
    credentials: MeetingCredentials;
    onLeave: () => void;
    /** What the call is about — the posting both sides are here for. */
    title: string;
    /** Who is on the other end, named while you wait for them. */
    participant: string;
}) {
    const [stage, setStage] = useState<Stage>('connecting');
    const [failure, setFailure] = useState<{
        message: string;
        hint: string | null;
    } | null>(null);

    /*
     * Bumped to retry. Granting a permission does not re-run the failed call,
     * so without this the only way back in is to leave and start again — and
     * the person has usually just fixed the very thing that stopped them.
     */
    const [attempt, setAttempt] = useState(0);
    const [micOn, setMicOn] = useState(true);
    const [cameraOn, setCameraOn] = useState(true);
    const [sharing, setSharing] = useState(false);
    const [peers, setPeers] = useState(0);

    /* Seconds since the call went live, for the clock in the corner. */
    const [elapsed, setElapsed] = useState(0);

    /* Says what is missing, without turning it into a failed call. */
    const [deviceNote, setDeviceNote] = useState<string | null>(null);

    const localRef = useRef<HTMLDivElement | null>(null);
    const remoteRef = useRef<HTMLDivElement | null>(null);

    const client = useRef<IAgoraRTCClient | null>(null);
    const micTrack = useRef<IMicrophoneAudioTrack | null>(null);
    const cameraTrack = useRef<ICameraVideoTrack | null>(null);
    const screenTrack = useRef<ILocalVideoTrack | null>(null);

    /*
     * The clock only runs once there is a call to time. Started off `stage`
     * rather than mount, so the seconds spent asking for a camera are not
     * counted as time in the meeting.
     */
    useEffect(() => {
        if (stage !== 'live') {
            return;
        }

        const id = window.setInterval(() => setElapsed((s) => s + 1), 1000);

        return () => clearInterval(id);
    }, [stage]);

    /**
     * Tear everything down. Safe to call twice — leaving and unmounting both
     * land here, and a half-joined call still has tracks holding the camera
     * light on if they are not closed.
     */
    const teardown = useCallback(async () => {
        for (const track of [micTrack, cameraTrack, screenTrack]) {
            track.current?.stop();
            track.current?.close();
            track.current = null;
        }

        try {
            await client.current?.leave();
        } catch {
            /* Already gone; nothing to salvage. */
        }

        client.current?.removeAllListeners();
        client.current = null;
    }, []);

    useEffect(() => {
        let cancelled = false;

        const join = async () => {
            try {
                const AgoraRTC = (await import('agora-rtc-sdk-ng')).default;

                if (cancelled) {
                    return;
                }

                const rtc = AgoraRTC.createClient({
                    mode: 'rtc',
                    codec: 'vp8',
                });
                client.current = rtc;

                rtc.on(
                    'user-published',
                    async (
                        user: IAgoraRTCRemoteUser,
                        media: 'audio' | 'video',
                    ) => {
                        await rtc.subscribe(user, media);

                        if (media === 'video' && remoteRef.current) {
                            user.videoTrack?.play(remoteRef.current);
                        }

                        if (media === 'audio') {
                            user.audioTrack?.play();
                        }

                        setPeers(rtc.remoteUsers.length);
                    },
                );

                rtc.on('user-left', () => setPeers(rtc.remoteUsers.length));
                rtc.on('user-unpublished', () =>
                    setPeers(rtc.remoteUsers.length),
                );

                await rtc.join(
                    credentials.appId,
                    credentials.channel,
                    credentials.token,
                    credentials.uid,
                );

                if (cancelled) {
                    return;
                }

                /*
                 * Each device is asked for separately, and neither is
                 * required. createMicrophoneAndCameraTracks() demands both in
                 * one call, so a missing webcam — or a refused camera
                 * permission — used to end the call before it began, with no
                 * way to join even to listen. Somebody without a camera should
                 * still be able to hear the meeting they were invited to.
                 */
                let mic: IMicrophoneAudioTrack | null = null;
                let camera: ICameraVideoTrack | null = null;

                try {
                    mic = await AgoraRTC.createMicrophoneAudioTrack();
                } catch {
                    /* No microphone, or it was refused. Join muted. */
                }

                try {
                    camera = await AgoraRTC.createCameraVideoTrack();
                } catch {
                    /* No camera, or it was refused. Join without video. */
                }

                if (cancelled) {
                    mic?.close();
                    camera?.close();

                    return;
                }

                micTrack.current = mic;
                cameraTrack.current = camera;

                setMicOn(mic !== null);
                setCameraOn(camera !== null);

                if (camera !== null && localRef.current) {
                    camera.play(localRef.current);
                }

                const publishing = [mic, camera].filter(
                    (
                        track,
                    ): track is IMicrophoneAudioTrack & ICameraVideoTrack =>
                        track !== null,
                );

                /* Nothing to publish is a valid way to be here: a viewer. */
                if (publishing.length > 0) {
                    await rtc.publish(publishing);
                }

                /*
                 * Said plainly, and as a note rather than an error — the call
                 * is running. Without this somebody joins to silence and
                 * assumes the meeting is broken rather than their microphone.
                 */
                if (mic === null && camera === null) {
                    setDeviceNote(
                        'You joined with no camera or microphone. You can see and hear everyone; use the buttons below to turn either on.',
                    );
                } else if (camera === null) {
                    setDeviceNote(
                        'You joined without a camera. They can hear you.',
                    );
                } else if (mic === null) {
                    setDeviceNote('You joined muted. They can see you.');
                }

                setStage('live');

                /* You are in. The one moment worth a sound of its own. */
                chime('on');
            } catch (thrown) {
                if (cancelled) {
                    return;
                }

                /*
                 * Overwhelmingly this is a refused camera or microphone
                 * permission, so say that rather than printing an SDK code at
                 * somebody who cannot act on it.
                 */
                setFailure(explain(thrown));
                setStage('failed');
            }
        };

        void join();

        return () => {
            cancelled = true;
            void teardown();
        };
    }, [credentials, teardown, attempt]);

    /**
     * Turn the microphone on or off — acquiring it first if joining had to go
     * without one.
     *
     * Somebody who refused the permission and then changed their mind, or who
     * plugged a headset in after joining, presses the same button they would
     * otherwise. Failing to get the device leaves the call alone: they are
     * still in the meeting, still muted, and can try again.
     */
    const toggleMic = async () => {
        const rtc = client.current;

        if (!rtc) {
            return;
        }

        if (!micTrack.current) {
            try {
                const AgoraRTC = (await import('agora-rtc-sdk-ng')).default;
                const mic = await AgoraRTC.createMicrophoneAudioTrack();

                micTrack.current = mic;
                await rtc.publish(mic);
                setMicOn(true);
                chime('on');
            } catch {
                setDeviceNote('Your microphone is still unavailable.');
            }

            return;
        }

        const next = !micOn;

        /*
         * setMuted, not setEnabled. setEnabled(false) releases the microphone
         * back to the operating system, so unmuting has to re-acquire the
         * hardware — which frequently failed, and is why mute was a one-way
         * trip. setMuted keeps the device held and simply stops sending, which
         * is what a mute button means everywhere else.
         */
        await micTrack.current.setMuted(!next);
        setMicOn(next);
        chime(next ? 'on' : 'off');
    };

    /**
     * The same for the camera, including the case where there was none to
     * start with.
     */
    const toggleCamera = async () => {
        const rtc = client.current;

        if (!rtc) {
            return;
        }

        if (!cameraTrack.current) {
            try {
                const AgoraRTC = (await import('agora-rtc-sdk-ng')).default;
                const camera = await AgoraRTC.createCameraVideoTrack();

                cameraTrack.current = camera;

                if (localRef.current) {
                    camera.play(localRef.current);
                }

                await rtc.publish(camera);
                setCameraOn(true);
                setDeviceNote(null);
                chime('on');
            } catch {
                setDeviceNote('Your camera is still unavailable.');
            }

            return;
        }

        const next = !cameraOn;

        /*
         * setEnabled here rather than setMuted, deliberately: it releases the
         * camera, so the light beside the lens actually goes out. A "camera
         * off" that leaves the light on is a lie worth avoiding.
         *
         * The cost is that turning it back on has to re-acquire the device and
         * can fail — so if it does, take a fresh track rather than leaving
         * somebody stuck off, which is exactly what happened to the microphone.
         */
        try {
            await cameraTrack.current.setEnabled(next);
            setCameraOn(next);
            chime(next ? 'on' : 'off');
        } catch {
            cameraTrack.current.close();
            cameraTrack.current = null;
            setCameraOn(false);
            setDeviceNote(
                'Your camera could not be turned back on. Press Camera on to try again.',
            );
        }
    };

    /**
     * Screen sharing replaces the camera on the wire rather than adding a
     * second video track: one video track per publisher is what the other side
     * knows how to lay out, and a second would arrive as a silent extra tile.
     */
    const toggleShare = async () => {
        const rtc = client.current;

        if (!rtc) {
            return;
        }

        try {
            if (sharing) {
                if (screenTrack.current) {
                    await rtc.unpublish(screenTrack.current);
                    screenTrack.current.stop();
                    screenTrack.current.close();
                    screenTrack.current = null;
                }

                if (cameraTrack.current) {
                    await rtc.publish(cameraTrack.current);

                    if (localRef.current) {
                        cameraTrack.current.play(localRef.current);
                    }
                }

                setSharing(false);
                chime('off');

                return;
            }

            const AgoraRTC = (await import('agora-rtc-sdk-ng')).default;
            const screen = await AgoraRTC.createScreenVideoTrack(
                { encoderConfig: '1080p_1' },
                'disable',
            );

            if (cameraTrack.current) {
                await rtc.unpublish(cameraTrack.current);
                cameraTrack.current.stop();
            }

            screenTrack.current = screen;
            await rtc.publish(screen);

            if (localRef.current) {
                screen.play(localRef.current);
            }

            /* Ending the share from the browser's own bar, not our button. */
            screen.on('track-ended', () => {
                void toggleShare();
            });

            setSharing(true);
            chime('on');
        } catch {
            /* Cancelling the picker throws; that is not an error worth showing. */
        }
    };

    const leave = async () => {
        chime('off');
        await teardown();
        onLeave();
    };

    const clock =
        String(Math.floor(elapsed / 60)).padStart(2, '0') +
        ':' +
        String(elapsed % 60).padStart(2, '0');

    const initial = participant.trim().charAt(0).toUpperCase() || '?';

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label="Video call"
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 60,
                background: 'var(--color-bg)',
                color: 'var(--color-text)',
                display: 'flex',
                flexDirection: 'column',
            }}
        >
            <div style={{ position: 'relative', flex: 1, minHeight: 0 }}>
                {/* What the call is about, and how long it has been running. */}
                <div
                    style={{
                        position: 'absolute',
                        inset: '18px 20px auto 20px',
                        zIndex: 2,
                        display: 'flex',
                        alignItems: 'center',
                        gap: 12,
                        pointerEvents: 'none',
                    }}
                >
                    <span style={PILL}>{title}</span>

                    <span
                        style={{
                            ...PILL,
                            marginLeft: 'auto',
                            fontVariantNumeric: 'tabular-nums',
                            color: MUTED,
                        }}
                        aria-label={`In the call for ${clock}`}
                    >
                        {clock}
                    </span>
                </div>

                <div
                    ref={remoteRef}
                    aria-label="The other participant"
                    style={{
                        width: '100%',
                        height: '100%',
                        background: 'var(--color-bg)',
                    }}
                />

                {stage !== 'live' && (
                    <div style={COVER}>
                        {stage === 'connecting' ? (
                            <span style={{ fontSize: 14, color: MUTED }}>
                                Connecting…
                            </span>
                        ) : (
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: 'center',
                                    gap: 10,
                                    maxWidth: 420,
                                    textAlign: 'center',
                                }}
                            >
                                <span style={{ fontSize: 15 }}>
                                    {failure?.message ??
                                        'The call could not be started.'}
                                </span>

                                {failure?.hint !== null &&
                                    failure?.hint !== undefined && (
                                        <span
                                            style={{
                                                fontSize: 13,
                                                lineHeight: 1.5,
                                                color: MUTED,
                                            }}
                                        >
                                            {failure.hint}
                                        </span>
                                    )}

                                <div
                                    style={{
                                        display: 'flex',
                                        gap: 10,
                                        marginTop: 4,
                                    }}
                                >
                                    <Btn
                                        variant="secondary"
                                        onClick={() => {
                                            setFailure(null);
                                            setStage('connecting');
                                            setAttempt(attempt + 1);
                                        }}
                                    >
                                        Try again
                                    </Btn>
                                    <Btn variant="ghost" onClick={leave}>
                                        Close
                                    </Btn>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/*
                 * Waiting names the person you are waiting for. "Waiting for
                 * the other side" alone reads as though nobody is expected.
                 */}
                {stage === 'live' && peers === 0 && (
                    <div style={COVER}>
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'center',
                                gap: 14,
                            }}
                        >
                            <span style={AVATAR}>{initial}</span>

                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: 'center',
                                    gap: 4,
                                }}
                            >
                                <span style={{ fontSize: 17 }}>
                                    {participant}
                                </span>
                                <span style={{ fontSize: 13, color: MUTED }}>
                                    Waiting for the other side to join…
                                </span>
                            </div>
                        </div>
                    </div>
                )}

                {/* Your own camera, small, over the corner. */}
                <div
                    style={{
                        position: 'absolute',
                        right: 18,
                        bottom: 18,
                        width: 'clamp(120px, 20vw, 200px)',
                        aspectRatio: '4 / 3',
                        borderRadius: 10,
                        overflow: 'hidden',
                        background: 'var(--color-surface)',
                        border: '1px solid var(--color-divider)',
                        boxShadow: '0 6px 22px rgba(0,0,0,0.10)',
                    }}
                >
                    <div
                        ref={localRef}
                        aria-label="Your camera"
                        style={{ width: '100%', height: '100%' }}
                    />

                    {/*
                     * Sits over the frame rather than replacing it: the div
                     * above is where Agora attaches the track, and swapping it
                     * out on camera state would drop the element it plays into.
                     */}
                    {!cameraOn && (
                        <span
                            style={{
                                position: 'absolute',
                                inset: 0,
                                display: 'grid',
                                placeItems: 'center',
                                color: MUTED,
                                fontSize: 20,
                            }}
                        >
                            <VideoCameraSlashIcon />
                        </span>
                    )}

                    <span
                        style={{
                            position: 'absolute',
                            left: 8,
                            bottom: 6,
                            fontSize: 11.5,
                            color: MUTED,
                        }}
                    >
                        You
                    </span>

                    {!micOn && (
                        <span
                            style={{
                                position: 'absolute',
                                right: 8,
                                bottom: 6,
                                fontSize: 13,
                                color: MUTED,
                            }}
                            aria-label="Your microphone is muted"
                        >
                            <MicrophoneSlashIcon />
                        </span>
                    )}
                </div>
            </div>

            {stage === 'live' && deviceNote !== null && (
                <div
                    style={{
                        padding: '9px clamp(12px, 4vw, 24px)',
                        background:
                            'color-mix(in srgb, var(--color-text) 6%, transparent)',
                        borderTop: '1px solid var(--color-divider)',
                        color: MUTED,
                        fontSize: 12.5,
                        textAlign: 'center',
                    }}
                >
                    {deviceNote}
                </div>
            )}

            <div
                style={{
                    display: 'flex',
                    flexWrap: 'wrap',
                    gap: 10,
                    justifyContent: 'center',
                    padding: '16px clamp(12px, 4vw, 24px)',
                    background: 'var(--color-surface)',
                    borderTop: '1px solid var(--color-divider)',
                }}
            >
                <Btn
                    variant="secondary"
                    style={CONTROL}
                    onClick={toggleMic}
                    disabled={stage !== 'live'}
                    aria-pressed={!micOn}
                    title={micOn ? 'Mute' : 'Unmute'}
                >
                    {micOn ? <MicrophoneIcon /> : <MicrophoneSlashIcon />}
                    {micOn ? 'Mute' : 'Unmute'}
                </Btn>

                <Btn
                    variant="secondary"
                    style={CONTROL}
                    onClick={toggleCamera}
                    disabled={stage !== 'live'}
                    aria-pressed={!cameraOn}
                    title={cameraOn ? 'Turn camera off' : 'Turn camera on'}
                >
                    {cameraOn ? <VideoCameraIcon /> : <VideoCameraSlashIcon />}
                    {cameraOn ? 'Camera off' : 'Camera on'}
                </Btn>

                <Btn
                    variant="secondary"
                    style={CONTROL}
                    onClick={toggleShare}
                    disabled={stage !== 'live'}
                    aria-pressed={sharing}
                    title={sharing ? 'Stop sharing' : 'Share your screen'}
                >
                    <MonitorArrowUpIcon />
                    {sharing ? 'Stop sharing' : 'Share screen'}
                </Btn>

                <Btn
                    variant="secondary"
                    style={{
                        ...CONTROL,
                        background:
                            'color-mix(in srgb, var(--color-text) 9%, transparent)',
                    }}
                    onClick={leave}
                    title="Leave the call"
                >
                    <PhoneDisconnectIcon />
                    Leave
                </Btn>
            </div>
        </div>
    );
}

const MUTED = 'color-mix(in srgb, var(--color-text) 55%, transparent)';

/** The two labels floating over the call. */
const PILL: React.CSSProperties = {
    padding: '6px 14px',
    borderRadius: 999,
    fontSize: 12.5,
    background: 'var(--color-surface)',
    border: '1px solid var(--color-divider)',
};

/** Anything drawn instead of a remote picture. */
const COVER: React.CSSProperties = {
    position: 'absolute',
    inset: 0,
    display: 'grid',
    placeItems: 'center',
    padding: 24,
};

/** The stand-in for somebody who has not arrived yet. */
const AVATAR: React.CSSProperties = {
    width: 84,
    height: 84,
    borderRadius: '50%',
    display: 'grid',
    placeItems: 'center',
    fontSize: 26,
    color: 'var(--color-accent)',
    background: 'color-mix(in srgb, var(--color-accent) 14%, transparent)',
};

/** The call controls read as one row of pills. */
const CONTROL: React.CSSProperties = {
    borderRadius: 999,
    paddingInline: 18,
};
