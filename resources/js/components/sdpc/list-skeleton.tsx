import { Panel } from '@/components/sdpc/panel';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/**
 * The placeholder a ranked list shows while it is being worked out.
 *
 * Get Client and Recruit paint their search and filters first and load the
 * ranked list behind this (a deferred prop), because ranking can wait on the
 * matching model for a few seconds. Pulsing rows in the shape of the list, so
 * the page reads as loading rather than empty.
 */
export function ListSkeleton({
    rows = 4,
    label,
}: {
    rows?: number;
    /** Said to assistive technology while the list loads. */
    label: string;
}) {
    return (
        <Panel padding="lg" gap="none" aria-busy="true" aria-label={label}>
            {Array.from({ length: rows }, (_, index) => (
                <div
                    key={index}
                    className="animate-pulse"
                    style={{
                        display: 'flex',
                        gap: 14,
                        alignItems: 'center',
                        padding: '14px 0',
                        borderTop:
                            index === 0 ? 'none' : `1px solid ${MUTED(8)}`,
                        animationDelay: `${index * 120}ms`,
                    }}
                >
                    <span
                        style={{
                            width: 38,
                            height: 38,
                            borderRadius: '50%',
                            background: MUTED(9),
                            flex: 'none',
                        }}
                    />
                    <span style={{ display: 'grid', gap: 8, flex: 1 }}>
                        <span
                            style={{
                                height: 11,
                                width: `${58 - index * 6}%`,
                                borderRadius: 6,
                                background: MUTED(10),
                            }}
                        />
                        <span
                            style={{
                                height: 9,
                                width: `${82 - index * 5}%`,
                                borderRadius: 6,
                                background: MUTED(7),
                            }}
                        />
                    </span>
                    <span
                        style={{
                            width: 64,
                            height: 22,
                            borderRadius: 11,
                            background: MUTED(8),
                            flex: 'none',
                        }}
                    />
                </div>
            ))}
            <span
                style={{
                    fontSize: 11.5,
                    color: MUTED(55),
                    paddingTop: 10,
                }}
            >
                {label}
            </span>
        </Panel>
    );
}
