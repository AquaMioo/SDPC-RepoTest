/**
 * Whole-day arithmetic on Y-m-d strings.
 *
 * The timeline works in calendar days, and a Y-m-d date has no time zone —
 * reading one through `new Date('2026-02-02')` parses it as UTC midnight,
 * which in Manila is already 8am and in some zones is the day before. So dates
 * are turned into a day count through Date.UTC and back, and never touch the
 * browser's local clock.
 *
 * "Today" is Singapore Time (GMT+8), the application's clock, whatever zone
 * the browser is in — so the date boxes allow exactly the days the server
 * accepts (App\Support\TimelineWindow).
 */

const MS_PER_DAY = 86_400_000;

/** The application's time zone: Singapore Time, GMT+8. */
export const APP_TIME_ZONE = 'Asia/Singapore';

const APP_DAY = new Intl.DateTimeFormat('en-US', {
    timeZone: APP_TIME_ZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

/** Days since the epoch for a Y-m-d string. */
export function dayNumber(isoDate: string): number {
    const [year, month, day] = isoDate.split('-').map(Number);

    return Math.round(Date.UTC(year, month - 1, day) / MS_PER_DAY);
}

/** The Y-m-d string for a day count. */
export function isoFromDayNumber(days: number): string {
    return new Date(days * MS_PER_DAY).toISOString().slice(0, 10);
}

/** Today in Singapore Time, as Y-m-d. */
export function todayIso(): string {
    const parts = Object.fromEntries(
        APP_DAY.formatToParts(new Date()).map((part) => [
            part.type,
            part.value,
        ]),
    );

    return `${parts.year}-${parts.month}-${parts.day}`;
}

/** Today in Singapore Time, as a day count. */
export function todayDayNumber(): number {
    return dayNumber(todayIso());
}

/**
 * The Y-m-d string a whole number of months later, without spilling into the
 * month after (31 Jan + 1 month is 28 Feb), as Carbon's addMonthsNoOverflow.
 */
export function addMonthsIso(isoDate: string, months: number): string {
    const [year, month, day] = isoDate.split('-').map(Number);
    const target = new Date(Date.UTC(year, month - 1 + months, 1));
    const lastDay = new Date(
        Date.UTC(target.getUTCFullYear(), target.getUTCMonth() + 1, 0),
    ).getUTCDate();

    target.setUTCDate(Math.min(day, lastDay));

    return target.toISOString().slice(0, 10);
}

/**
 * The days any timeline date may be set to: today to one year from today,
 * Singapore Time. App\Support\TimelineWindow holds the server to the same.
 */
export function timelineWindow(): { min: string; max: string } {
    const today = todayIso();
    const [year, month, day] = today.split('-').map(Number);

    return {
        min: today,
        /* Carbon's addYear: 29 Feb runs on to 1 Mar, as Date.UTC does. */
        max: new Date(Date.UTC(year + 1, month - 1, day))
            .toISOString()
            .slice(0, 10),
    };
}

/** The first day a Turnover starting on the given day may end: a month on. */
export function earliestTurnoverEnd(startsOn: string): string {
    return addMonthsIso(startsOn, 1);
}

/** The last day a Turnover ending on the given day may start: a month before. */
export function latestTurnoverStart(endsOn: string): string {
    return addMonthsIso(endsOn, -1);
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

/** Roughly how many days a month spans, for spacing an axis. */
const DAYS_PER_MONTH = 30.44;

/** Every nth month an axis may label, from densest to sparsest. */
const MONTH_STEPS = [1, 2, 3, 6, 12] as const;

/** "2026" for a day count. */
function yearOf(days: number): number {
    return new Date(days * MS_PER_DAY).getUTCFullYear();
}

/** 0 for January … 11 for December, for a day count. */
function monthIndexOf(days: number): number {
    return new Date(days * MS_PER_DAY).getUTCMonth();
}

/**
 * The month starts worth labelling on an axis of a given density.
 *
 * Every month when there is room for it; otherwise every 2nd, 3rd, 6th or
 * 12th, always on calendar-aligned months (Jan/Apr/Jul/Oct for quarters) so a
 * reader can count along them. Labels never come closer than `minGapPx`, which
 * is what stopped a two-year build from printing 24 labels on top of each
 * other. The year is written on the first label and on every January, so an
 * axis that crosses New Year says which one.
 */
export function monthTicks(
    from: number,
    to: number,
    pxPerDay: number,
    minGapPx = 56,
): { day: number; label: string }[] {
    const starts = monthStarts(from, to);

    if (starts.length === 0) {
        return [];
    }

    const monthPx = pxPerDay * DAYS_PER_MONTH;
    const step =
        MONTH_STEPS.find((each) => monthPx * each >= minGapPx) ??
        MONTH_STEPS[MONTH_STEPS.length - 1];

    const chosen = starts.filter((day) => monthIndexOf(day) % step === 0);
    const ticks = chosen.length > 0 ? chosen : [starts[0]];

    return ticks.map((day, index) => {
        const needsYear =
            index === 0 ||
            monthIndexOf(day) === 0 ||
            yearOf(day) !== yearOf(ticks[index - 1]);

        return {
            day,
            label: needsYear
                ? `${monthLabel(day)} ${yearOf(day)}`
                : monthLabel(day),
        };
    });
}

/** The pixel width an axis needs so no month gets less than `pxPerMonth`. */
export function minimumAxisWidth(totalDays: number, pxPerMonth = 64): number {
    return Math.ceil((totalDays / DAYS_PER_MONTH) * pxPerMonth);
}
