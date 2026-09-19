import { Head, Link, usePage } from '@inertiajs/react';
import {
    BriefcaseIcon,
    CertificateIcon,
    MagnifyingGlassIcon,
} from '@phosphor-icons/react';

import { Btn } from '@/components/sdpc/btn';
import { Panel, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { projectManagement } from '@/routes';
import { show as agreementShow } from '@/routes/agreements';
import { index as studentBoard } from '@/routes/student/board';
import type { AgreementListItem } from '@/types/agreements';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

const TAG_VARIANT: Record<string, 'accent' | 'neutral' | 'outline'> = {
    accent: 'accent',
    neutral: 'neutral',
    outline: 'outline',
};

type Props = {
    agreements: AgreementListItem[];
};

/**
 * The page before any contract exists.
 *
 * Centred in the space the list would fill, with no box around it — the
 * style the team picked on 2026-09-19 over a lone line of text in a panel.
 * The button goes where an agreement actually starts: a client accepts a
 * student on Project Management; a student applies from Find a client.
 */
function NoAgreements() {
    const team = useCurrentTeam();
    const isStudent =
        usePage<{ auth?: { role?: string | null } }>().props.auth?.role ===
        'student';

    return (
        <div
            style={{
                minHeight: 'min(58vh, 520px)',
                display: 'grid',
                placeItems: 'center',
                textAlign: 'center',
                padding: '24px 0',
            }}
        >
            <div
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    gap: 10,
                    maxWidth: 440,
                }}
            >
                <CertificateIcon
                    aria-hidden="true"
                    size={42}
                    style={{ color: 'var(--color-accent-400)' }}
                />

                <div style={{ fontSize: 20, lineHeight: 1.3 }}>
                    No agreements yet
                </div>

                <p
                    style={{
                        margin: 0,
                        fontSize: 13.5,
                        lineHeight: 1.6,
                        color: MUTED(62),
                    }}
                >
                    {isStudent
                        ? 'One is drafted the moment a client accepts you, and the work starts when both sides have signed it.'
                        : 'One is drafted the moment you accept a student, and the work starts when both sides have signed it.'}
                </p>

                <Btn asChild variant="primary" style={{ marginTop: 8 }}>
                    <Link
                        href={
                            isStudent
                                ? studentBoard.url(team.slug)
                                : projectManagement.url(team.slug)
                        }
                    >
                        {isStudent ? (
                            <>
                                <MagnifyingGlassIcon />
                                Find a client
                            </>
                        ) : (
                            <>
                                <BriefcaseIcon />
                                Go to Project Management
                            </>
                        )}
                    </Link>
                </Btn>
            </div>
        </div>
    );
}

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
                    maxWidth: 'clamp(1060px, 100vw - 320px, 1600px)',
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
                    <NoAgreements />
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
                                        data-quiet=""
                                        style={{
                                            fontSize: 13.5,
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
