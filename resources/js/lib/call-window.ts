import { toast } from 'sonner';
import { window as meetingWindow } from '@/routes/meetings';

const WINDOW_NAME = 'sdpc-call';
const WIDTH = 1100;
const HEIGHT = 760;

/** What the call window tells Messenger when it closes, so the thread re-reads. */
export const CALL_WINDOW_CLOSED = 'sdpc:call-window-closed';

const POPUP_BLOCKED = 'Please allow pop-ups to open the call window.';

let opened: Window | null = null;

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
        if (url !== undefined) {
            opened.location.href = url;
        }

        opened.focus();

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

    return opened;
}

/** Point an already open (blank) call window at a meeting. */
export function showCall(
    target: Window,
    teamSlug: string,
    meetingId: number,
): void {
    target.location.href = callWindowUrl(teamSlug, meetingId);
}

/** The call window's own address for a meeting. */
export function callWindowUrl(teamSlug: string, meetingId: number): string {
    return meetingWindow.url({ current_team: teamSlug, meeting: meetingId });
}
