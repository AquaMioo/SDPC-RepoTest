import { Head, Link, router } from '@inertiajs/react';

import { Btn } from '@/components/sdpc/btn';
import { Panel, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { update as milestoneUpdate } from '@/routes/agreements/milestones';
import { index as boardIndex } from '@/routes/student/board';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

const TAG_VARIANT: Record<string, 'accent' | 'neutral' | 'outline'> = {
    accent: 'accent',
    neutral: 'neutral',
    outline: 'outline',
};

const peso = new Intl.NumberFormat('en-PH');

type Milestone = {
    id: number;
    position: number;
    title: string;
    description: string | null;
    amount: number;
    startsOn: string | null;
    endsOn: string | null;
    status: string;
    statusLabel: string;
    statusVariant: 'outline' | 'accent' | 'accent-2' | 'neutral';
    reviewNote: string | null;
    isFinal: boolean;
};

type Props = {
    agreement: {
        id: number;
        reference: string;
        projectTitle: string;
        client: string;
        startsOn: string | null;
        endsOn: string | null;
        /* The share of milestones the client has approved, and its terms. */
        progress: number;
        approvedCount: number;
        milestoneCount: number;
        milestones: Milestone[];
    } | null;
    assignableStatuses: { value: string; label: string }[];
};

/**
 * "Project process" — where the build actually stands.
 *
 * The ring counts the milestones the client has approved, and each milestone
 * shows the status somebody recorded. Every one of those transitions was
 * caused by a person, which is the only
 * honest progress figure the platform has: nothing here is inferred from a
 * date having passed.
 */
export default function StudentProcess({
    agreement,
    assignableStatuses,
}: Props) {
    const team = useCurrentTeam();

    if (agreement === null) {
        return (
            <>
                <Head title="Project process" />

                <div
                    style={{
                        maxWidth: 'clamp(1160px, 100vw - 320px, 1600px)',
                        margin: '0 auto',
                        padding: '30px clamp(16px, 4vw, 32px) 72px',
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 18,
                    }}
                >
                    <div>
                        <h3 style={{ margin: 0 }}>Project process</h3>
                        <div style={{ fontSize: 13, color: MUTED(60) }}>
                            Where your current build stands.
                        </div>
                    </div>

                    <Panel padding="lg" gap="sm">
                        <span style={{ fontSize: 13 }}>
                            No signed agreement yet.
                        </span>
                        <span style={{ fontSize: 12.5, color: MUTED(65) }}>
                            Progress is tracked against the milestones in a
                            contract, so this screen fills in once a client has
                            accepted you and both sides have signed.
                        </span>
                        <Btn
                            asChild
                            variant="secondary"
                            style={{ alignSelf: 'start' }}
                        >
                            <Link href={boardIndex.url(team.slug)}>
                                Find work
                            </Link>
                        </Btn>
                    </Panel>
                </div>
            </>
        );
    }

    const circumference = 2 * Math.PI * 52;

    const move = (milestone: Milestone, status: string) => {
        router.patch(
            milestoneUpdate.url({
                current_team: team.slug,
                agreement: agreement.id,
                milestone: milestone.id,
            }),
            { status },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Project process" />

            <div
                style={{
                    maxWidth: 'clamp(1160px, 100vw - 320px, 1600px)',
                    margin: '0 auto',
                    padding: '30px clamp(16px, 4vw, 32px) 72px',
                }}
            >
                <div style={{ marginBottom: 22 }}>
                    <h3 style={{ margin: 0 }}>Project process</h3>
                    <div style={{ fontSize: 13, color: MUTED(60) }}>
                        {agreement.projectTitle} · {agreement.client} · contract{' '}
                        {agreement.reference}
                    </div>
                </div>

                <Panel
                    style={{
                        padding: '24px 26px',
                        gap: 0,
                        flexDirection: 'row',
                        alignItems: 'center',
                        marginBottom: 26,
                    }}
                >
                    <div
                        style={{
                            position: 'relative',
                            width: 132,
                            height: 132,
                            flex: 'none',
                            marginRight: 34,
                        }}
                    >
                        <svg
                            width={132}
                            height={132}
                            viewBox="0 0 120 120"
                            style={{ transform: 'rotate(-90deg)' }}
                        >
                            <circle
                                cx={60}
                                cy={60}
                                r={52}
                                fill="none"
                                stroke="var(--color-divider)"
                                strokeWidth={11}
                            />
                            <circle
                                cx={60}
                                cy={60}
                                r={52}
                                fill="none"
                                stroke="var(--color-accent)"
                                strokeWidth={11}
                                strokeLinecap="round"
                                strokeDasharray={circumference}
                                strokeDashoffset={
                                    circumference *
                                    (1 - agreement.progress / 100)
                                }
                            />
                        </svg>
                        <div
                            style={{
                                position: 'absolute',
                                inset: 0,
                                display: 'grid',
                                placeItems: 'center',
                                fontFamily: 'var(--font-heading)',
                                fontSize: 26,
                            }}
                        >
                            {agreement.progress}%
                        </div>
                    </div>

                    <div
                        style={{
                            flex: 1,
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 12,
                        }}
                    >
                        <Bar
                            label={`${agreement.approvedCount} of ${agreement.milestoneCount} approved`}
                            value={agreement.progress}
                        />

                        {/*
                         * Each milestone shows the status a person recorded.
                         * It used to show a bar filled to 40% for "in
                         * progress" and 80% for "submitted" — numbers nobody
                         * ever supplied, sitting next to real ones.
                         */}
                        {agreement.milestones.map((milestone) => (
                            <div
                                key={milestone.id}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                    fontSize: 12.5,
                                }}
                            >
                                <span
                                    style={{
                                        marginRight: 'auto',
                                        minWidth: 0,
                                        overflow: 'hidden',
                                        textOverflow: 'ellipsis',
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    {milestone.title}
                                </span>
                                <Tag variant={milestone.statusVariant}>
                                    {milestone.statusLabel}
                                </Tag>
                            </div>
                        ))}
                    </div>
                </Panel>

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'baseline',
                        gap: 12,
                        marginBottom: 16,
                    }}
                >
                    <h4 style={{ margin: 0, marginRight: 'auto' }}>
                        Milestones
                    </h4>
                    <span style={{ fontSize: 12, color: MUTED(68) }}>
                        {agreement.startsOn} – {agreement.endsOn}
                    </span>
                </div>

                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 16,
                    }}
                >
                    {agreement.milestones.map((milestone) => (
                        <Panel
                            key={milestone.id}
                            style={{ padding: 18, gap: 10 }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                }}
                            >
                                <PanelKicker>
                                    Milestone {milestone.position}
                                </PanelKicker>
                                <span
                                    style={{
                                        fontFamily: 'var(--font-heading)',
                                        fontSize: 16,
                                        marginRight: 'auto',
                                    }}
                                >
                                    {milestone.title}
                                </span>
                                <span
                                    style={{
                                        fontSize: 12.5,
                                        color: MUTED(70),
                                        fontVariantNumeric: 'tabular-nums',
                                    }}
                                >
                                    ₱ {peso.format(milestone.amount)}
                                </span>
                                <Tag
                                    variant={
                                        TAG_VARIANT[milestone.statusVariant] ??
                                        'neutral'
                                    }
                                >
                                    {milestone.statusLabel}
                                </Tag>
                            </div>

                            {milestone.description && (
                                <p
                                    style={{
                                        margin: 0,
                                        fontSize: 12.5,
                                        lineHeight: 1.55,
                                        color: MUTED(60),
                                    }}
                                >
                                    {milestone.description}
                                </p>
                            )}

                            <div style={{ fontSize: 11.5, color: MUTED(68) }}>
                                {milestone.startsOn ?? '—'} –{' '}
                                {milestone.endsOn ?? '—'}
                            </div>

                            {milestone.reviewNote && (
                                <div
                                    style={{
                                        fontSize: 12,
                                        lineHeight: 1.55,
                                        padding: '10px 12px',
                                        borderRadius: 'var(--radius-md)',
                                        background:
                                            'color-mix(in srgb, var(--color-accent) 8%, transparent)',
                                    }}
                                >
                                    <strong>From the client:</strong>{' '}
                                    {milestone.reviewNote}
                                </div>
                            )}

                            {!milestone.isFinal && (
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                    }}
                                >
                                    <span
                                        style={{
                                            fontSize: 11.5,
                                            color: MUTED(68),
                                            marginRight: 'auto',
                                        }}
                                    >
                                        Approval is the client's to give.
                                    </span>
                                    {assignableStatuses
                                        .filter(
                                            (status) =>
                                                status.value !==
                                                milestone.status,
                                        )
                                        .map((status) => (
                                            <Btn
                                                key={status.value}
                                                onClick={() =>
                                                    move(
                                                        milestone,
                                                        status.value,
                                                    )
                                                }
                                            >
                                                Mark{' '}
                                                {status.label.toLowerCase()}
                                            </Btn>
                                        ))}
                                </div>
                            )}
                        </Panel>
                    ))}
                </div>
            </div>
        </>
    );
}

/** The design's labelled progress bar, as the Project process screen draws it. */
function Bar({ label, value }: { label: string; value: number }) {
    return (
        <div>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    fontSize: 12.5,
                    marginBottom: 5,
                }}
            >
                <span>{label}</span>
                <span style={{ color: 'var(--color-accent)' }}>{value}%</span>
            </div>
            <div
                role="progressbar"
                aria-label={label}
                aria-valuenow={value}
                aria-valuemin={0}
                aria-valuemax={100}
                style={{
                    height: 6,
                    borderRadius: 3,
                    background: 'var(--color-divider)',
                }}
            >
                <div
                    style={{
                        width: `${value}%`,
                        height: '100%',
                        borderRadius: 3,
                        background: 'var(--color-accent)',
                    }}
                />
            </div>
        </div>
    );
}
