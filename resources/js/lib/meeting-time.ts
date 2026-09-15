/**
 * Wording for a booked meeting's time, in the viewer's own time zone.
 *
 * Kept apart from the component that draws meetings: the dashboard calendars
 * need the day a meeting falls on without pulling in any of the rendering, and
 * a module with no JSX can be run and checked on its own.
 *
 * All of it happens in the browser rather than on the server. The application
 * runs on UTC, so the server's "today" is not the viewer's — a meeting at 7:00
 * in the morning in Manila falls on the previous day in UTC, and a
 * server-written "Tomorrow" would be wrong for exactly the bookings people make
 * first thing.
 */

const DAY = 86_400_000;

/**
 * Midnight on the viewer's own calendar, as a timestamp.
 */
function startOfLocalDay(date: Date): number {
    return new Date(
        date.getFullYear(),
        date.getMonth(),
        date.getDate(),
    ).getTime();
}

/**
 * The meeting's day on the viewer's calendar, as YYYY-MM-DD.
 *
 * Built from the local date parts rather than from toISOString(), which is UTC
 * and would move anything booked before 8am in Manila back onto the day before.
 */
export function localDateKey(iso: string): string {
    const at = new Date(iso);

    return [
        at.getFullYear(),
        String(at.getMonth() + 1).padStart(2, '0'),
        String(at.getDate()).padStart(2, '0'),
    ].join('-');
}

/**
 * "Today at 3:00 PM", "Tomorrow at 9:30 AM", "Wednesday at 2:00 PM", or a date.
 *
 * Math.round rather than floor across the day boundary, so a daylight-saving
 * shift of an hour cannot turn tomorrow into today.
 */
export function whenLabel(iso: string, now: Date = new Date()): string {
    const at = new Date(iso);
    const days = Math.round((startOfLocalDay(at) - startOfLocalDay(now)) / DAY);

    const time = at.toLocaleTimeString(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    });

    /*
     * Meetings stay listed for an hour after their start, so somebody running
     * late still finds theirs. Say so rather than calling a meeting that has
     * already begun "today" as though there were time to spare.
     */
    if (at.getTime() <= now.getTime()) {
        return `Started at ${time}`;
    }

    if (days === 0) {
        return `Today at ${time}`;
    }

    if (days === 1) {
        return `Tomorrow at ${time}`;
    }

    if (days > 1 && days < 7) {
        return `${at.toLocaleDateString(undefined, { weekday: 'long' })} at ${time}`;
    }

    return `${at.toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    })} at ${time}`;
}
