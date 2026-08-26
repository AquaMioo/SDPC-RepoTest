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
}: {
    credentials: MeetingCredentials;
    onLeave: () => void;
}) {
    const [stage, setStage] = useState<Stage>('connecting');
    const [error, setError] = useState<string | null>(null);
    const [micOn, setMicOn] = useState(true);
    const [cameraOn, setCameraOn] = useState(true);
    const [sharing, setSharing] = useState(false);
    const [peers, setPeers] = useState(0);

    const localRef = useRef<HTMLDivElement | null>(null);
    const remoteRef = useRef<HTMLDivElement | null>(null);

    const client = useRef<IAgoraRTCClient | null>(null);
    const micTrack = useRef<IMicrophoneAudioTrack | null>(null);
    const cameraTrack = useRef<ICameraVideoTrack | null>(null);
    const screenTrack = useRef<ILocalVideoTrack | null>(null);

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

                const [mic, camera] =
                    await AgoraRTC.createMicrophoneAndCameraTracks();

                micTrack.current = mic;
                cameraTrack.current = camera;

                if (cancelled) {
                    return;
                }

                if (localRef.current) {
                    camera.play(localRef.current);
                }

                await rtc.publish([mic, camera]);

                setStage('live');
            } catch (thrown) {
                if (cancelled) {
                    return;
                }

                /*
                 * Overwhelmingly this is a refused camera or microphone
                 * permission, so say that rather than printing an SDK code at
                 * somebody who cannot act on it.
                 */
                setError(
                    thrown instanceof Error
                        ? thrown.message
                        : 'The call could not be started.',
                );
                setStage('failed');
            }
        };

        void join();

        return () => {
            cancelled = true;
            void teardown();
        };
    }, [credentials, teardown]);

    const toggleMic = async () => {
        if (!micTrack.current) {
            return;
        }

        const next = !micOn;
        await micTrack.current.setEnabled(next);
        setMicOn(next);
    };

    const toggleCamera = async () => {
        if (!cameraTrack.current) {
            return;
        }

        const next = !cameraOn;
        await cameraTrack.current.setEnabled(next);
        setCameraOn(next);
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
        } catch {
            /* Cancelling the picker throws; that is not an error worth showing. */
        }
    };

    const leave = async () => {
        await teardown();
        onLeave();
    };

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label="Video call"
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 60,
                background: '#0d120f',
                display: 'flex',
                flexDirection: 'column',
            }}
        >
            <div style={{ position: 'relative', flex: 1, minHeight: 0 }}>
                <div
                    ref={remoteRef}
                    aria-label="The other participant"
                    style={{
                        width: '100%',
                        height: '100%',
                        background: '#0d120f',
                    }}
                />

                {stage !== 'live' && (
                    <div
                        style={{
                            position: 'absolute',
                            inset: 0,
                            display: 'grid',
                            placeItems: 'center',
                            color: '#e6efea',
                            fontSize: 14,
                            textAlign: 'center',
                            padding: 24,
                        }}
                    >
                        {stage === 'connecting'
                            ? 'Connecting…'
                            : (error ?? 'The call could not be started.')}
                    </div>
                )}

                {stage === 'live' && peers === 0 && (
                    <div
                        style={{
                            position: 'absolute',
                            inset: 0,
                            display: 'grid',
                            placeItems: 'center',
                            color: 'rgba(230,239,234,0.65)',
                            fontSize: 14,
                        }}
                    >
                        Waiting for the other side to join…
                    </div>
                )}

                {/* Your own camera, small, over the corner. */}
                <div
                    ref={localRef}
                    aria-label="Your camera"
                    style={{
                        position: 'absolute',
                        right: 16,
                        bottom: 16,
                        width: 'clamp(120px, 22vw, 220px)',
                        aspectRatio: '4 / 3',
                        borderRadius: 8,
                        overflow: 'hidden',
                        background: '#1b2f28',
                        boxShadow: '0 6px 22px rgba(0,0,0,0.45)',
                    }}
                />
            </div>

            <div
                style={{
                    display: 'flex',
                    flexWrap: 'wrap',
                    gap: 10,
                    justifyContent: 'center',
                    padding: '14px clamp(12px, 4vw, 24px)',
                    background: '#111713',
                    borderTop: '1px solid rgba(230,239,234,0.12)',
                }}
            >
                <Btn
                    variant="secondary"
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
                    onClick={toggleShare}
                    disabled={stage !== 'live'}
                    aria-pressed={sharing}
                    title={sharing ? 'Stop sharing' : 'Share your screen'}
                >
                    <MonitorArrowUpIcon />
                    {sharing ? 'Stop sharing' : 'Share screen'}
                </Btn>

                <Btn variant="primary" onClick={leave} title="Leave the call">
                    <PhoneDisconnectIcon />
                    Leave
                </Btn>
            </div>
        </div>
    );
}
