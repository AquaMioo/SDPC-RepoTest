/**
 * A phone ring, drawn with the Web Audio API.
 *
 * Synthesised rather than shipped as a sound file: two short bursts of the
 * familiar 440 + 480 Hz dual tone, every three seconds. Nothing to download,
 * nothing to license, and it stops the moment the returned function is called.
 *
 * Browsers only let a page make sound once the person has interacted with it.
 * Someone who has been using the platform already has; on a tab nobody has
 * touched yet the context stays suspended and the ring is silent, which is why
 * the incoming-call card is visual first and the sound is a bonus.
 */

type AudioContextConstructor = typeof AudioContext;

const RING_EVERY_MS = 3000;
const BURST_STARTS = [0, 0.6];
const BURST_SECONDS = 0.4;
const TONES_HZ = [440, 480];
const VOLUME = 0.12;

export function startRingtone(): () => void {
    const Context: AudioContextConstructor | undefined =
        window.AudioContext ??
        (window as unknown as { webkitAudioContext?: AudioContextConstructor })
            .webkitAudioContext;

    if (Context === undefined) {
        return () => {};
    }

    let context: AudioContext;

    try {
        context = new Context();
    } catch {
        return () => {};
    }

    void context.resume().catch(() => {});

    let stopped = false;

    const ringOnce = (): void => {
        if (stopped) {
            return;
        }

        const now = context.currentTime;

        for (const start of BURST_STARTS) {
            const gain = context.createGain();
            const from = now + start;
            const to = from + BURST_SECONDS;

            gain.gain.setValueAtTime(0, from);
            gain.gain.linearRampToValueAtTime(VOLUME, from + 0.02);
            gain.gain.setValueAtTime(VOLUME, to - 0.02);
            gain.gain.linearRampToValueAtTime(0, to);
            gain.connect(context.destination);

            for (const frequency of TONES_HZ) {
                const oscillator = context.createOscillator();

                oscillator.frequency.value = frequency;
                oscillator.connect(gain);
                oscillator.start(from);
                oscillator.stop(to + 0.02);
            }
        }
    };

    ringOnce();

    const timer = window.setInterval(ringOnce, RING_EVERY_MS);

    return () => {
        stopped = true;
        window.clearInterval(timer);
        void context.close().catch(() => {});
    };
}
