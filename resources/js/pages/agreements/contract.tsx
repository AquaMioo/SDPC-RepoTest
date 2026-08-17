import { Head, Link } from '@inertiajs/react';
import { ShieldCheckIcon } from '@phosphor-icons/react';

import SignatureForm from '@/components/agreements/signature-form';
import { Btn } from '@/components/sdpc/btn';
import { Panel, PanelDivider, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { show as agreementShow } from '@/routes/agreements';
import type { Agreement } from '@/types/agreements';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/** A written date the way the contract quotes one: "Oct 15, 2026". */
function longDate(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return new Date(value).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

type Props = {
    agreement: Agreement;
};

/**
 * The Contract screen — the same document as the Agreement screen, read in
 * full rather than summarised.
 *
 * The clauses are the ones the client wrote on their own agreement, not a
 * fixed platform contract: the app supplies a default text and gets out of the
 * way. Nothing here is legal advice.
 */
export default function AgreementContract({ agreement }: Props) {
    const team = useCurrentTeam();

    const clauses = [
        {
            heading: '1 · Intellectual property ownership',
            body: agreement.terms.intellectualProperty,
        },
        {
            heading: '2 · Confidentiality & data protection',
            body: agreement.terms.confidentiality,
        },
        {
            heading: '3 · Academic standards & capstone alignment',
            body: agreement.terms.academic,
        },
    ];

    return (
        <>
            <Head title="Contract" />

            <div
                style={{
                    maxWidth: 1000,
                    margin: '0 auto',
                    padding: '28px 32px 72px',
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-end',
                        gap: 16,
                        marginBottom: 6,
                    }}
                >
                    <div style={{ marginRight: 'auto' }}>
                        <h3 style={{ margin: 0 }}>Terms &amp; agreement</h3>
                        <div style={{ fontSize: 12.5, color: MUTED(68) }}>
                            Please review carefully before you accept
                        </div>
                    </div>
                    <Tag variant="outline">
                        <ShieldCheckIcon style={{ marginRight: 5 }} />
                        Contract v{agreement.version} · {agreement.reference}
                    </Tag>
                </div>

                <Panel
                    gap="lg"
                    style={{ marginTop: 18, padding: '22px 24px' }}
                >
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1.4fr 1fr 1fr',
                            gap: 20,
                        }}
                    >
                        <div>
                            <Kicker>Project title</Kicker>
                            <div
                                style={{
                                    fontFamily: 'var(--font-heading)',
                                    fontSize: 19,
                                }}
                            >
                                {agreement.project.title}
                            </div>
                            <div style={{ fontSize: 11.5, color: MUTED(68) }}>
                                {agreement.project.category} ·{' '}
                                {agreement.client.name}
                            </div>
                        </div>
                        <div>
                            <Kicker>Client representative</Kicker>
                            <div style={{ fontSize: 13.5 }}>
                                {agreement.client.signatoryName ??
                                    agreement.client.name}
                            </div>
                            <div style={{ fontSize: 11.5, color: MUTED(68) }}>
                                Terms commencement ·{' '}
                                {longDate(agreement.startsOn) ?? 'not set'}
                            </div>
                        </div>
                        <div>
                            <Kicker>Lead developer</Kicker>
                            <div style={{ fontSize: 13.5 }}>
                                {agreement.student.name} · #
                                {agreement.student.id}
                            </div>
                            <div style={{ fontSize: 11.5, color: MUTED(68) }}>
                                Final delivery ·{' '}
                                {longDate(agreement.endsOn) ?? 'not set'}
                            </div>
                        </div>
                    </div>

                    <PanelDivider />

                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 20,
                        }}
                    >
                        {agreement.scopeSummary && (
                            <div>
                                <h5 style={{ margin: '0 0 6px' }}>
                                    Scope of work
                                </h5>
                                <p style={CLAUSE_BODY}>
                                    {agreement.scopeSummary}
                                </p>

                                {agreement.deliverables.length > 0 && (
                                    <div
                                        style={{
                                            display: 'flex',
                                            flexDirection: 'column',
                                            gap: 6,
                                            marginTop: 8,
                                            ...CLAUSE_BODY,
                                        }}
                                    >
                                        {agreement.deliverables.map(
                                            (deliverable) => (
                                                <div key={deliverable}>
                                                    · {deliverable}
                                                </div>
                                            ),
                                        )}
                                    </div>
                                )}
                            </div>
                        )}

                        {clauses.map((clause) => (
                            <div key={clause.heading}>
                                <h5 style={{ margin: '0 0 6px' }}>
                                    {clause.heading}
                                </h5>
                                <p style={CLAUSE_BODY}>
                                    {clause.body ??
                                        'The client has not written this clause yet.'}
                                </p>
                            </div>
                        ))}

                        <div>
                            <h5 style={{ margin: '0 0 10px' }}>
                                4 · Deliverables &amp; schedule
                            </h5>
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Phase</th>
                                        <th>Milestone</th>
                                        <th style={{ textAlign: 'right' }}>
                                            Deadline
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {agreement.milestones.map(
                                        (milestone, index) => (
                                            <tr key={milestone.id}>
                                                <td style={{ width: 90 }}>
                                                    {index ===
                                                    agreement.milestones
                                                        .length -
                                                        1
                                                        ? 'Final'
                                                        : `Phase ${index + 1}`}
                                                </td>
                                                <td>
                                                    {milestone.description ??
                                                        milestone.title}
                                                </td>
                                                <td
                                                    style={{
                                                        textAlign: 'right',
                                                    }}
                                                >
                                                    {longDate(
                                                        milestone.endsOn,
                                                    ) ?? 'not set'}
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </Panel>

                <div style={{ marginTop: 16 }}>
                    <SignatureForm
                        agreement={agreement}
                        tone="accent"
                        leading={
                            <Btn asChild>
                                <Link
                                    href={agreementShow.url({
                                        current_team: team.slug,
                                        agreement: agreement.id,
                                    })}
                                >
                                    Back to summary
                                </Link>
                            </Btn>
                        }
                    />
                </div>

                {agreement.signatures.length > 0 && (
                    <Panel
                        gap="md"
                        style={{ marginTop: 16, padding: '18px 24px' }}
                    >
                        <PanelKicker>Contract log</PanelKicker>
                        {agreement.signatures.map((signature) => (
                            <div
                                key={signature.party}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                    fontSize: 12.5,
                                }}
                            >
                                <span style={{ marginRight: 'auto' }}>
                                    {signature.signedName} ·{' '}
                                    {signature.partyLabel} · account #
                                    {signature.accountId}
                                </span>
                                <span style={{ color: MUTED(68) }}>
                                    {signature.signedAt}
                                </span>
                            </div>
                        ))}
                    </Panel>
                )}
            </div>
        </>
    );
}

const CLAUSE_BODY = {
    margin: 0,
    fontSize: 13,
    lineHeight: 1.65,
    color: MUTED(65),
} as const;

function Kicker({ children }: { children: string }) {
    return (
        <div
            style={{
                fontSize: 10.5,
                letterSpacing: '.08em',
                textTransform: 'uppercase',
                color: MUTED(68),
                marginBottom: 5,
            }}
        >
            {children}
        </div>
    );
}
