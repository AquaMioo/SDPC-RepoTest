import { Link } from '@inertiajs/react';
import { VideoCameraIcon } from '@phosphor-icons/react';

import { Panel } from '@/components/sdpc/panel';
import { whenLabel } from '@/lib/meeting-time';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

export type UpcomingMeeting = {
    id: number;
    /** An exact ISO-8601 instant, worded in the browser — see lib/meeting-time. */
    scheduledAt: string;
    with: string;
    project: string;
    scheduledByMe: boolean;
    url: string;
};

/**
 * The meetings booked in the viewer's threads, soonest first.
 *
 * Shared by the client and student dashboards so a booking reads the same from
 * both sides — only the name of who it is with changes.
 */
export function UpcomingMeetingsPanel({
    meetings,
}: {
    meetings: UpcomingMeeting[];
}) {
    const now = new Date();

    return (
        <Panel padding="md" gap="sm">
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <VideoCameraIcon style={{ color: 'var(--color-accent)' }} />
                <span style={{ fontSize: 13 }}>Upcoming meetings</span>
            </div>

            {meetings.length === 0 ? (
                <span style={{ fontSize: 12, color: MUTED(58) }}>
                    Nothing booked. Schedule a meeting from a conversation in
                    Messages and it will show up here.
                </span>
            ) : (
                <div style={{ display: 'flex', flexDirection: 'column' }}>
                    {meetings.map((meeting, index) => {
                        const label = whenLabel(meeting.scheduledAt, now);
                        const isSoon =
                            label.startsWith('Today') ||
                            label.startsWith('Started');

                        return (
                            <Link
                                key={meeting.id}
                                href={meeting.url}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 12,
                                    padding: '10px 0',
                                    borderTop:
                                        index === 0
                                            ? undefined
                                            : `1px solid ${MUTED(10)}`,
                                    color: 'inherit',
                                    textDecoration: 'none',
                                }}
                            >
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div
                                        style={{
                                            fontSize: 13.5,
                                            fontWeight: 600,
                                            color: isSoon
                                                ? 'var(--color-accent)'
                                                : 'var(--color-text)',
                                        }}
                                    >
                                        {label}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: MUTED(72),
                                            overflow: 'hidden',
                                            textOverflow: 'ellipsis',
                                            whiteSpace: 'nowrap',
                                        }}
                                    >
                                        With {meeting.with} · {meeting.project}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            color: MUTED(50),
                                        }}
                                    >
                                        {meeting.scheduledByMe
                                            ? 'You booked it'
                                            : `${meeting.with} booked it`}
                                    </div>
                                </div>

                                <span
                                    style={{
                                        fontSize: 12,
                                        color: 'var(--color-accent)',
                                        flexShrink: 0,
                                    }}
                                >
                                    Open thread
                                </span>
                            </Link>
                        );
                    })}
                </div>
            )}
        </Panel>
    );
}
