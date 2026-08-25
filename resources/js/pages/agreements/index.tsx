import { Head, Link } from '@inertiajs/react';

import { Panel, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { show as agreementShow } from '@/routes/agreements';
import type { AgreementListItem } from '@/types/agreements';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

const TAG_VARIANT: Record<string, 'accent' | 'neutral' | 'outline'> = {
    accent: 'accent',
    neutral: 'neutral',
    outline: 'outline',
};

const peso = new Intl.NumberFormat('en-PH');

type Props = {
    agreements: AgreementListItem[];
};

/**
 * The fallback list.
 *
 * Both caps in the platform — one posting per business, one build per student
 * — mean a single standing agreement is the normal case, and the controller
 * redirects straight to it. This screen is what a superseded history or a
 * second business looks like.
 */
export default function AgreementIndex({ agreements }: Props) {
    const team = useCurrentTeam();

    return (
        <>
            <Head title="Agreements" />

            <div
                style={{
                    maxWidth: 1060,
                    margin: '0 auto',
                    padding: '30px clamp(16px, 4vw, 32px) 72px',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 22,
                }}
            >
                <div>
                    <h3 style={{ margin: 0 }}>Agreements</h3>
                    <div style={{ fontSize: 13, color: MUTED(60) }}>
                        Every contract you are a party to, current and past.
                    </div>
                </div>

                {agreements.length === 0 ? (
                    <Panel padding="lg" gap="sm">
                        <span style={{ fontSize: 13 }}>No agreement yet.</span>
                        <span style={{ fontSize: 12.5, color: MUTED(65) }}>
                            One is drafted the moment a client accepts a
                            student, and the work starts when both sides have
                            signed it.
                        </span>
                    </Panel>
                ) : (
                    <Panel padding="lg" gap="md">
                        <PanelKicker>{agreements.length} in total</PanelKicker>

                        {agreements.map((agreement) => (
                            <div
                                key={agreement.id}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 12,
                                    paddingTop: 10,
                                    borderTop: '1px solid var(--color-divider)',
                                }}
                            >
                                <div
                                    style={{ marginRight: 'auto', minWidth: 0 }}
                                >
                                    <Link
                                        href={agreementShow.url({
                                            current_team: team.slug,
                                            agreement: agreement.id,
                                        })}
                                        style={{
                                            fontSize: 13.5,
                                            color: 'var(--color-text)',
                                            textDecoration: 'none',
                                        }}
                                    >
                                        {agreement.projectTitle}
                                    </Link>
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            color: MUTED(65),
                                        }}
                                    >
                                        {agreement.counterparty} ·{' '}
                                        {agreement.reference} · v
                                        {agreement.version}
                                    </div>
                                </div>

                                <span
                                    style={{
                                        fontSize: 12.5,
                                        fontVariantNumeric: 'tabular-nums',
                                        color: MUTED(70),
                                    }}
                                >
                                    ₱ {peso.format(agreement.totalAmount)}
                                </span>

                                <Tag
                                    variant={
                                        TAG_VARIANT[agreement.statusVariant] ??
                                        'neutral'
                                    }
                                >
                                    {agreement.statusLabel}
                                </Tag>
                            </div>
                        ))}
                    </Panel>
                )}
            </div>
        </>
    );
}
