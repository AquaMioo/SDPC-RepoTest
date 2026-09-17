import { Head, Link } from '@inertiajs/react';
import { DownloadSimpleIcon, ShieldCheckIcon } from '@phosphor-icons/react';
import { toast } from 'sonner';

import SignatureForm from '@/components/agreements/signature-form';
import { Btn } from '@/components/sdpc/btn';
import { Panel, PanelDivider, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { useCurrentTeam } from '@/hooks/use-current-team';
import {
    memorandum as memorandumDownload,
    show as agreementShow,
} from '@/routes/agreements';
import type { Agreement, Memorandum } from '@/types/agreements';

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
 * New agreements read as the school's Memorandum of Agreement, with its blanks
 * filled from the agreement. One that somebody signed before the memorandum
 * arrived keeps the clauses it was signed against. Nothing here is legal
 * advice.
 */
export default function AgreementContract({ agreement }: Props) {
    const team = useCurrentTeam();
    const memorandum = agreement.memorandum;

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
                    maxWidth: 'clamp(1000px, 100vw - 320px, 1600px)',
                    margin: '0 auto',
                    padding: '28px clamp(16px, 4vw, 32px) 72px',
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
                        <h3 style={{ margin: 0 }}>
                            {memorandum?.title ?? 'Terms & agreement'}
                        </h3>
                        <div style={{ fontSize: 12.5, color: MUTED(68) }}>
                            Please review carefully before you accept
                        </div>
                    </div>
                    {memorandum ? (
                        /*
                         * The school's blank Memorandum of Agreement, as a PDF
                         * to print and sign by hand. A plain link rather than
                         * an Inertia one: it is a file, not a page.
                         */
                        <a
                            href={memorandumDownload.url({
                                current_team: team.slug,
                                agreement: agreement.id,
                            })}
                            download
                            title="Download the Memorandum of Agreement (PDF)"
                            data-test="download-memorandum"
                            className="tag-link"
                            /*
                             * The browser saves the file quietly, often
                             * with nothing on the page to show it, so the
                             * click says so itself.
                             */
                            onClick={() =>
                                toast.success(
                                    'Downloading the Memorandum of Agreement…',
                                    {
                                        description: `${agreement.reference} Memorandum of Agreement.pdf`,
                                    },
                                )
                            }
                        >
                            <Tag variant="outline">
                                <DownloadSimpleIcon
                                    style={{ marginRight: 5 }}
                                />
                                Contract v{agreement.version} ·{' '}
                                {agreement.reference} · PDF
                            </Tag>
                        </a>
                    ) : (
                        <Tag variant="outline">
                            <ShieldCheckIcon style={{ marginRight: 5 }} />
                            Contract v{agreement.version} ·{' '}
                            {agreement.reference}
                        </Tag>
                    )}
                </div>

                <Panel gap="lg" style={{ marginTop: 18, padding: '22px 24px' }}>
                    <div
                        className="stack"
                        style={{
                            ['--cols' as string]: '1.4fr 1fr 1fr',
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
                                {memorandum
                                    ? 'Takes effect'
                                    : 'Terms commencement'}{' '}
                                · {longDate(agreement.startsOn) ?? 'not set'}
                            </div>
                        </div>
                        <div>
                            <Kicker>
                                {memorandum
                                    ? 'Student developer / team leader'
                                    : 'Lead developer'}
                            </Kicker>
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

                    {memorandum ? (
                        <MemorandumBody
                            agreement={agreement}
                            memorandum={memorandum}
                        />
                    ) : (
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
                                <ScheduleTable agreement={agreement} />
                            </div>
                        </div>
                    )}
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

/**
 * The school's Memorandum of Agreement, in the same layout the earlier
 * clauses used: the parties and purpose, the approved scope, one numbered
 * heading per part, the schedule, and the signature lines.
 */
function MemorandumBody({
    agreement,
    memorandum,
}: {
    agreement: Agreement;
    memorandum: Memorandum;
}) {
    const signatureOf = (party: string) =>
        agreement.signatures.find((signature) => signature.party === party);

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
            <div>
                <p style={CLAUSE_BODY}>
                    Between{' '}
                    <strong style={PARTY}>{memorandum.parties.client}</strong>,
                    and{' '}
                    <strong style={PARTY}>
                        {memorandum.parties.developer}
                    </strong>
                    ,
                </p>
                <p style={{ ...CLAUSE_BODY, marginTop: 10 }}>
                    {memorandum.purpose}
                </p>
                <Bullets items={memorandum.commitments} />
            </div>

            {agreement.scopeSummary && (
                <div>
                    <h5 style={{ margin: '0 0 6px' }}>Approved scope</h5>
                    <p style={CLAUSE_BODY}>{agreement.scopeSummary}</p>
                    <Bullets items={agreement.deliverables} />
                </div>
            )}

            {memorandum.sections.map((section, index) => (
                <div key={section.heading}>
                    <h5 style={{ margin: '0 0 6px' }}>
                        {index + 1} · {section.heading}
                    </h5>
                    {section.body && (
                        <p style={CLAUSE_BODY}>
                            <Emphasised text={section.body} />
                        </p>
                    )}
                    <Bullets items={section.items} />
                </div>
            ))}

            <div>
                <h5 style={{ margin: '0 0 10px' }}>
                    {memorandum.sections.length + 1} · Deliverables &amp;
                    schedule
                </h5>
                <ScheduleTable agreement={agreement} />
            </div>

            <div>
                <p style={CLAUSE_BODY}>{memorandum.closing}</p>
                <p style={{ ...CLAUSE_BODY, marginTop: 10 }}>
                    {memorandum.signedOn ??
                        'Signed on SDPC once both parties have put their names to it below.'}
                </p>
            </div>

            <PanelDivider />

            <div
                className="stack"
                style={{ ['--cols' as string]: '1fr 1fr', gap: 20 }}
            >
                {(
                    [
                        ['Client representative', 'client'],
                        ['Student developer / team leader', 'student'],
                    ] as const
                ).map(([label, party]) => {
                    const signature = signatureOf(party);

                    return (
                        <div key={party}>
                            <Kicker>{label}</Kicker>
                            <SignLine
                                label="Name"
                                value={signature?.signedName ?? null}
                            />
                            <SignLine
                                label="Signature"
                                value={
                                    signature
                                        ? `Signed electronically · account #${signature.accountId}`
                                        : null
                                }
                            />
                            <SignLine
                                label="Date"
                                value={signature?.signedAt ?? null}
                            />
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

/** The phases and their deadlines, as both wordings show them. */
function ScheduleTable({ agreement }: { agreement: Agreement }) {
    return (
        <div className="table-wrap">
            <table className="table">
                <thead>
                    <tr>
                        <th>Phase</th>
                        <th>Milestone</th>
                        <th style={{ textAlign: 'right' }}>Deadline</th>
                    </tr>
                </thead>
                <tbody>
                    {agreement.milestones.map((milestone, index) => (
                        <tr key={milestone.id}>
                            <td style={{ width: 90 }}>
                                {index === agreement.milestones.length - 1
                                    ? 'Final'
                                    : `Phase ${index + 1}`}
                            </td>
                            <td>{milestone.description ?? milestone.title}</td>
                            <td style={{ textAlign: 'right' }}>
                                {longDate(milestone.endsOn) ?? 'not set'}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/** A short list, drawn the way the scope's deliverables always were. */
function Bullets({ items }: { items: string[] }) {
    if (items.length === 0) {
        return null;
    }

    return (
        <div
            style={{
                display: 'flex',
                flexDirection: 'column',
                gap: 6,
                marginTop: 8,
                ...CLAUSE_BODY,
            }}
        >
            {items.map((item) => (
                <div key={item}>· {item}</div>
            ))}
        </div>
    );
}

/**
 * Text with the **bold** runs the paper form prints, drawn as strong text.
 * Only that marker is read — nothing in the text is treated as markup.
 */
function Emphasised({ text }: { text: string }) {
    return (
        <>
            {text.split(/\*\*(.+?)\*\*/).map((part, index) =>
                index % 2 === 1 ? (
                    <strong key={index} style={PARTY}>
                        {part}
                    </strong>
                ) : (
                    part
                ),
            )}
        </>
    );
}

/** "Name: ____" — the value once there is one, a blank line until then. */
function SignLine({ label, value }: { label: string; value: string | null }) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'baseline',
                gap: 8,
                fontSize: 12.5,
                marginTop: 8,
            }}
        >
            <span style={{ color: MUTED(68), flex: 'none' }}>{label}:</span>
            <span
                style={{
                    flex: 1,
                    minWidth: 0,
                    paddingBottom: 2,
                    borderBottom: '1px solid var(--color-divider)',
                    color: value ? 'var(--color-text)' : MUTED(40),
                }}
            >
                {value ?? '\u00a0'}
            </span>
        </div>
    );
}

/** The names the memorandum fills its blanks with, and its bold runs. */
const PARTY = {
    color: 'var(--color-text)',
    fontWeight: 600,
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
