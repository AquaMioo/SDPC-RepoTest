import { toast } from 'sonner';
import { window as meetingWindow } from '@/routes/meetings';

const WINDOW_NAME = 'sdpc-call';
const WIDTH = 1100;
const HEIGHT = 760;

/** What the call window tells Messenger when it closes, so the thread re-reads. */
export const CALL_WINDOW_CLOSED = 'sdpc:call-window-closed';

/** Said whenever a call window opens, so every ringing card goes quiet. */
const CALL_JOINED = 'sdpc:call-joined';

const POPUP_BLOCKED = 'Please allow pop-ups to open the call window.';

let opened: Window | null = null;

/** Other tabs ring too, so the word goes to them as well. */
const tabs: BroadcastChannel | null =
    typeof BroadcastChannel === 'undefined'
        ? null
        : new BroadcastChannel(CALL_JOINED);

/** Tell this page and the person's other tabs that a call was joined. */
function announceCallJoined(): void {
    window.dispatchEvent(new Event(CALL_JOINED));
    tabs?.postMessage(CALL_JOINED);
}

/**
 * Run `silence` whenever any Join or Answer opens a call window, here or in
 * another tab. Returns the unsubscribe.
 */
export function onCallJoined(silence: () => void): () => void {
    window.addEventListener(CALL_JOINED, silence);
    tabs?.addEventListener('message', silence);

    return () => {
        window.removeEventListener(CALL_JOINED, silence);
        tabs?.removeEventListener('message', silence);
    };
}

/**
 * Whether the call window already shows this meeting.
 *
 * Pointing it there again reloads it, the reload's pagehide leaves the call,
 * and the other person, now alone, hangs up — a second Join ended the call.
 */
function isShowing(target: Window, url: string): boolean {
    try {
        return (
            target.location.pathname ===
            new URL(url, window.location.origin).pathname
        );
    } catch {
        return false;
    }
}

/**
 * Open the call in a window of its own, or bring forward the one already open.
 *
 * Has to run straight from the click: browsers let a page open a window only
 * as the direct result of a user action. When the call is not known yet (a
 * new call is still being created), pass no url — the window opens blank at
 * once and is pointed at the call with `showCall` once the server answers.
 *
 * Returns null, and says so, when the browser blocked the window.
 */
export function openCallWindow(url?: string): Window | null {
    if (opened !== null && !opened.closed) {
        if (url !== undefined && !isShowing(opened, url)) {
            opened.location.href = url;
        }

        opened.focus();
        announceCallJoined();

        return opened;
    }

    const left = Math.max(0, Math.round((window.screen.width - WIDTH) / 2));
    const top = Math.max(0, Math.round((window.screen.height - HEIGHT) / 2));

    opened = window.open(
        url ?? 'about:blank',
        WINDOW_NAME,
        `popup=yes,width=${WIDTH},height=${HEIGHT},left=${left},top=${top}`,
    );

    if (opened === null) {
        toast.error(POPUP_BLOCKED);

        return null;
    }

    opened.focus();
    announceCallJoined();

    return opened;
}

/**
 * Point an already open (blank) call window at a meeting — unless it is
 * already in that meeting, as when Join call is pressed during the call.
 */
export function showCall(
    target: Window,
    teamSlug: string,
    meetingId: number,
): void {
    const url = callWindowUrl(teamSlug, meetingId);

    if (!isShowing(target, url)) {
        target.location.href = url;
    }
}

/** The call window's own address for a meeting. */
export function callWindowUrl(teamSlug: string, meetingId: number): string {
    return meetingWindow.url({ current_team: teamSlug, meeting: meetingId });
}
