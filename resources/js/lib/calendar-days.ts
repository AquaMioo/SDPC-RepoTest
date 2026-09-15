/**
 * Whole-day arithmetic on Y-m-d strings.
 *
 * The timeline works in calendar days, and a Y-m-d date has no time zone —
 * reading one through `new Date('2026-02-02')` parses it as UTC midnight,
 * which in Manila is already 8am and in some zones is the day before. So dates
 * are turned into a day count through Date.UTC and back, and never touch the
 * browser's local clock except for "today".
 */

const MS_PER_DAY = 86_400_000;

/** Days since the epoch for a Y-m-d string. */
export function dayNumber(isoDate: string): number {
    const [year, month, day] = isoDate.split('-').map(Number);

    return Math.round(Date.UTC(year, month - 1, day) / MS_PER_DAY);
}

/** The Y-m-d string for a day count. */
export function isoFromDayNumber(days: number): string {
    return new Date(days * MS_PER_DAY).toISOString().slice(0, 10);
}

/** Today, as the person looking at the screen counts it. */
export function todayDayNumber(): number {
    const now = new Date();

    return Math.round(
        Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()) / MS_PER_DAY,
    );
}

const SHORT = new Intl.DateTimeFormat('en', {
    month: 'short',
    day: 'numeric',
    timeZone: 'UTC',
});

/** "Feb 2" for a Y-m-d string. */
export function shortDate(isoDate: string | null): string {
    return isoDate === null
        ? '—'
        : SHORT.format(new Date(dayNumber(isoDate) * MS_PER_DAY));
}

const MONTH = new Intl.DateTimeFormat('en', {
    month: 'short',
    timeZone: 'UTC',
});

/** "FEB" for a day count. */
export function monthLabel(days: number): string {
    return MONTH.format(new Date(days * MS_PER_DAY)).toUpperCase();
}

/** The first day of each month that starts inside [from, to]. */
export function monthStarts(from: number, to: number): number[] {
    const first = new Date(from * MS_PER_DAY);
    let cursor = Date.UTC(first.getUTCFullYear(), first.getUTCMonth(), 1);

    if (cursor < from * MS_PER_DAY) {
        const next = new Date(cursor);
        cursor = Date.UTC(next.getUTCFullYear(), next.getUTCMonth() + 1, 1);
    }

    const starts: number[] = [];

    while (cursor <= to * MS_PER_DAY) {
        starts.push(Math.round(cursor / MS_PER_DAY));
        const next = new Date(cursor);
        cursor = Date.UTC(next.getUTCFullYear(), next.getUTCMonth() + 1, 1);
    }

    return starts;
}
