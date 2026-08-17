import { Head, Link, useForm } from '@inertiajs/react';
import {
    ClockIcon,
    CurrencyCircleDollarIcon,
    ListChecksIcon,
    UserIcon,
} from '@phosphor-icons/react';
import type { ReactNode } from 'react';
import { useState } from 'react';

import SignatureForm from '@/components/agreements/signature-form';
import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import { Input, Textarea } from '@/components/sdpc/input';
import { Panel } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { useCurrentTeam } from '@/hooks/use-current-team';
import {
    contract as agreementContract,
    update as agreementUpdate,
} from '@/routes/agreements';
import type { Agreement } from '@/types/agreements';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/** Which tag colour each status wears, mirroring the enum's tagVariant(). */
const TAG_VARIANT: Record<string, 'accent' | 'neutral' | 'outline'> = {
    accent: 'accent',
    neutral: 'neutral',
    outline: 'outline',
};

/** The design's three timeline rails, deepening down the phases. */
const PHASE_COLOURS = [
    'var(--color-accent)',
    'var(--color-accent-700)',
    'var(--color-accent-800)',
];

const peso = new Intl.NumberFormat('en-PH');

/** "₱ 8,000", the way the design writes money. */
function money(amount: number): string {
    return `₱ ${peso.format(amount)}`;
}

/** "9–27 Mar", collapsing the month when both ends share one. */
function dateRange(startsOn: string | null, endsOn: string | null): string {
    const start = startsOn ? new Date(startsOn) : null;
    const end = endsOn ? new Date(endsOn) : null;

    const dayMonth = (date: Date) =>
        date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });

    if (start && end) {
        return start.getMonth() === end.getMonth()
            ? `${start.getDate()}–${dayMonth(end)}`
            : `${dayMonth(start)} – ${dayMonth(end)}`;
    }

    if (start ?? end) {
        return dayMonth((start ?? end) as Date);
    }

    return 'Dates not set';
}

type MilestoneDraft = {
    id: number | null;
    title: string;
    description: string;
    amount: string;
    starts_on: string;
    ends_on: string;
};

type TermsDraft = {
    scope_summary: string;
    /** Edited as one-per-line text; split into an array on submit. */
    deliverables: string;
    intellectual_property_terms: string;
    confidentiality_terms: string;
    academic_terms: string;
    starts_on: string;
    ends_on: string;
    milestones: MilestoneDraft[];
};

type Props = {
    agreement: Agreement;
};

/**
 * "Terms & agreement" — the contract as a one-screen summary.
 *
 * One component for both parties. The client fills the figures in and the
 * student reads them; who gets which affordance comes from `viewer`, never
 * from the account type, so AgreementPolicy stays the single answer.
 */
export default function AgreementShow({ agreement }: Props) {
    const team = useCurrentTeam();
    const [isEditing, setIsEditing] = useState(false);

    const { viewer } = agreement;

    const form = useForm<TermsDraft>({
        scope_summary: agreement.scopeSummary ?? '',
        deliverables: agreement.deliverables.join('\n'),
        intellectual_property_terms: agreement.terms.intellectualProperty ?? '',
        confidentiality_terms: agreement.terms.confidentiality ?? '',
        academic_terms: agreement.terms.academic ?? '',
        starts_on: agreement.startsOn ?? '',
        ends_on: agreement.endsOn ?? '',
        milestones: agreement.milestones.map((milestone) => ({
            id: milestone.id,
            title: milestone.title,
            description: milestone.description ?? '',
            amount: String(milestone.amount),
            starts_on: milestone.startsOn ?? '',
            ends_on: milestone.endsOn ?? '',
        })),
    });

    const setMilestone = (
        index: number,
        key: keyof MilestoneDraft,
        value: string,
    ) => {
        form.setData(
            'milestones',
            form.data.milestones.map((milestone, position) =>
                position === index ? { ...milestone, [key]: value } : milestone,
            ),
        );
    };

    /*
     * Milestones go up whole rather than one at a time: reordering, renaming
     * and repricing are one negotiation, and SaveAgreementRequest reads them
     * as a set.
     */
    const save = () => {
        form.transform((data) => ({
            ...data,
            deliverables: data.deliverables
                .split('\n')
                .map((line) => line.trim())
                .filter((line) => line !== ''),
            starts_on: data.starts_on || null,
            ends_on: data.ends_on || null,
            milestones: data.milestones.map((milestone) => ({
                ...milestone,
                amount: Number(milestone.amount || 0),
                starts_on: milestone.starts_on || null,
                ends_on: milestone.ends_on || null,
            })),
        }));

        form.patch(
            agreementUpdate.url({
                current_team: team.slug,
                agreement: agreement.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => setIsEditing(false),
            },
        );
    };

    /** The running total while editing; the saved figure otherwise. */
    const total = isEditing
        ? form.data.milestones.reduce(
              (sum, milestone) => sum + Number(milestone.amount || 0),
              0,
          )
        : agreement.totalAmount;

    return (
        <>
            <Head title="Terms & agreement" />

            <div
                style={{
                    maxWidth: 1240,
                    margin: '0 auto',
                    padding: '30px 32px 72px',
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-end',
                        gap: 16,
                        marginBottom: 22,
                    }}
                >
                    <div style={{ marginRight: 'auto' }}>
                        <h3 style={{ margin: 0 }}>Terms &amp; agreement</h3>
                        <div style={{ fontSize: 13, color: MUTED(68) }}>
                            {agreement.project.title} · contract{' '}
                            {agreement.reference}
                            {agreement.version > 1
                                ? ` · v${agreement.version}`
                                : ''}
                        </div>
                    </div>

                    {viewer.canEdit && !isEditing && (
                        <Btn onClick={() => setIsEditing(true)}>Edit terms</Btn>
                    )}

                    <Tag
                        variant={
                            TAG_VARIANT[agreement.statusVariant] ?? 'neutral'
                        }
                    >
                        {agreement.statusLabel}
                    </Tag>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1.3fr 1.3fr 1.3fr 1fr',
                        gap: 14,
                        alignItems: 'stretch',
                        marginBottom: 26,
                    }}
                >
                    <PartyCard
                        label="Client"
                        name={agreement.client.name}
                        signatoryName={agreement.client.signatoryName}
                        accent
                    />

                    <Panel style={{ padding: 18, gap: 8 }}>
                        <CardHeading icon={<ListChecksIcon />} label="Scope" />

                        {isEditing ? (
                            <>
                                <Textarea
                                    aria-label="Scope"
                                    value={form.data.scope_summary}
                                    maxLength={5000}
                                    placeholder="What is being built, in a sentence or two."
                                    onChange={(event) =>
                                        form.setData(
                                            'scope_summary',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.scope_summary}
                                    className="text-[11px]"
                                />
                                <Textarea
                                    aria-label="Deliverables, one per line"
                                    value={form.data.deliverables}
                                    placeholder="One deliverable per line"
                                    onChange={(event) =>
                                        form.setData(
                                            'deliverables',
                                            event.target.value,
                                        )
                                    }
                                />
                            </>
                        ) : (
                            <>
                                <p
                                    style={{
                                        margin: 0,
                                        fontSize: 11.5,
                                        lineHeight: 1.55,
                                        color: MUTED(58),
                                    }}
                                >
                                    {agreement.scopeSummary ??
                                        'The client has not written the scope yet.'}
                                </p>
                                <div
                                    style={{
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 5,
                                        fontSize: 11.5,
                                        color: MUTED(70),
                                    }}
                                >
                                    {agreement.deliverables.map(
                                        (deliverable) => (
                                            <span key={deliverable}>
                                                · {deliverable}
                                            </span>
                                        ),
                                    )}
                                </div>
                            </>
                        )}
                    </Panel>

                    <Panel style={{ padding: 18, gap: 8 }}>
                        <CardHeading
                            icon={<CurrencyCircleDollarIcon />}
                            label="Pricing"
                        />

                        <table
                            style={{
                                width: '100%',
                                fontSize: 11.5,
                                borderCollapse: 'collapse',
                                color: MUTED(70),
                            }}
                        >
                            <tbody>
                                {isEditing
                                    ? form.data.milestones.map(
                                          (milestone, index) => (
                                              <tr key={index}>
                                                  <td style={{ padding: '3px 0' }}>
                                                      <Input
                                                          aria-label={`Milestone ${index + 1} title`}
                                                          value={milestone.title}
                                                          maxLength={120}
                                                          onChange={(event) =>
                                                              setMilestone(
                                                                  index,
                                                                  'title',
                                                                  event.target
                                                                      .value,
                                                              )
                                                          }
                                                      />
                                                  </td>
                                                  <td
                                                      style={{
                                                          width: 110,
                                                          paddingLeft: 8,
                                                      }}
                                                  >
                                                      <Input
                                                          aria-label={`Milestone ${index + 1} amount`}
                                                          type="number"
                                                          min={0}
                                                          value={milestone.amount}
                                                          onChange={(event) =>
                                                              setMilestone(
                                                                  index,
                                                                  'amount',
                                                                  event.target
                                                                      .value,
                                                              )
                                                          }
                                                      />
                                                  </td>
                                              </tr>
                                          ),
                                      )
                                    : agreement.milestones.map(
                                          (milestone, index) => (
                                              <tr key={milestone.id}>
                                                  <td style={{ padding: '3px 0' }}>
                                                      Milestone {index + 1} ·{' '}
                                                      {milestone.title}
                                                  </td>
                                                  <td
                                                      style={{
                                                          textAlign: 'right',
                                                      }}
                                                  >
                                                      {money(milestone.amount)}
                                                  </td>
                                              </tr>
                                          ),
                                      )}

                                <tr>
                                    <td
                                        style={{
                                            padding: '7px 0 0',
                                            color: 'var(--color-text)',
                                        }}
                                    >
                                        Total
                                    </td>
                                    <td
                                        style={{
                                            textAlign: 'right',
                                            paddingTop: 7,
                                            color: 'var(--color-accent)',
                                        }}
                                    >
                                        {money(total)}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <InputError
                            message={form.errors.milestones}
                            className="text-[11px]"
                        />
                    </Panel>

                    <Panel style={{ padding: 18, gap: 8 }}>
                        <CardHeading icon={<ClockIcon />} label="Timeline" />

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 7,
                                fontSize: 11.5,
                                color: MUTED(70),
                            }}
                        >
                            {isEditing
                                ? form.data.milestones.map(
                                      (milestone, index) => (
                                          <PhaseRow
                                              key={index}
                                              index={index}
                                              heading={`Phase ${index + 1} · ${milestone.title}`}
                                          >
                                              <div
                                                  style={{
                                                      display: 'flex',
                                                      gap: 6,
                                                      marginTop: 4,
                                                  }}
                                              >
                                                  <Input
                                                      aria-label={`Phase ${index + 1} start`}
                                                      type="date"
                                                      value={milestone.starts_on}
                                                      onChange={(event) =>
                                                          setMilestone(
                                                              index,
                                                              'starts_on',
                                                              event.target.value,
                                                          )
                                                      }
                                                  />
                                                  <Input
                                                      aria-label={`Phase ${index + 1} end`}
                                                      type="date"
                                                      value={milestone.ends_on}
                                                      onChange={(event) =>
                                                          setMilestone(
                                                              index,
                                                              'ends_on',
                                                              event.target.value,
                                                          )
                                                      }
                                                  />
                                              </div>
                                          </PhaseRow>
                                      ),
                                  )
                                : agreement.milestones.map(
                                      (milestone, index) => (
                                          <PhaseRow
                                              key={milestone.id}
                                              index={index}
                                              heading={`Phase ${index + 1} · ${dateRange(milestone.startsOn, milestone.endsOn)}`}
                                          >
                                              {milestone.description ??
                                                  milestone.title}
                                          </PhaseRow>
                                      ),
                                  )}
                        </div>
                    </Panel>

                    <PartyCard
                        label="Student"
                        name={agreement.student.name}
                        signatoryName={agreement.student.signatoryName}
                    />
                </div>

                {isEditing && (
                    <Panel
                        gap="lg"
                        style={{ padding: '22px 24px', marginBottom: 26 }}
                    >
                        <h6 style={{ margin: 0 }}>Contract terms</h6>
                        <p style={{ margin: 0, fontSize: 12, color: MUTED(65) }}>
                            These are the clauses the student reads in full on
                            the contract screen before signing.
                        </p>

                        <TermField
                            label="Intellectual property"
                            value={form.data.intellectual_property_terms}
                            error={form.errors.intellectual_property_terms}
                            onChange={(value) =>
                                form.setData(
                                    'intellectual_property_terms',
                                    value,
                                )
                            }
                        />
                        <TermField
                            label="Confidentiality & data protection"
                            value={form.data.confidentiality_terms}
                            error={form.errors.confidentiality_terms}
                            onChange={(value) =>
                                form.setData('confidentiality_terms', value)
                            }
                        />
                        <TermField
                            label="Academic standards"
                            value={form.data.academic_terms}
                            error={form.errors.academic_terms}
                            onChange={(value) =>
                                form.setData('academic_terms', value)
                            }
                        />

                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                            }}
                        >
                            <span
                                style={{
                                    fontSize: 11.5,
                                    color: MUTED(68),
                                    marginRight: 'auto',
                                }}
                            >
                                Terms lock the moment either side signs.
                            </span>
                            <Btn onClick={() => setIsEditing(false)}>Cancel</Btn>
                            <Btn
                                variant="primary"
                                disabled={form.processing}
                                onClick={save}
                            >
                                {form.processing ? 'Saving…' : 'Save terms'}
                            </Btn>
                        </div>
                    </Panel>
                )}

                <SignatureForm
                    agreement={agreement}
                    leading={
                        <Btn asChild>
                            <Link
                                href={agreementContract.url({
                                    current_team: team.slug,
                                    agreement: agreement.id,
                                })}
                            >
                                Read full contract
                            </Link>
                        </Btn>
                    }
                />
            </div>
        </>
    );
}

/**
 * One side of the contract, with whether they have put their name to it.
 */
function PartyCard({
    label,
    name,
    signatoryName,
    accent = false,
}: {
    label: string;
    name: string;
    signatoryName: string | null;
    accent?: boolean;
}) {
    return (
        <Panel
            style={{
                padding: 18,
                gap: 10,
                alignItems: 'center',
                textAlign: 'center',
                justifyContent: 'center',
            }}
        >
            <span
                style={{
                    width: 56,
                    height: 56,
                    borderRadius: '50%',
                    background: accent
                        ? 'var(--color-accent-800)'
                        : 'var(--color-neutral-800)',
                    display: 'grid',
                    placeItems: 'center',
                    color: accent
                        ? 'var(--color-accent-200)'
                        : 'var(--color-neutral-300)',
                    fontSize: 26,
                }}
            >
                <UserIcon />
            </span>
            <div style={{ fontFamily: 'var(--font-heading)', fontSize: 15 }}>
                {label}
            </div>
            <div style={{ fontSize: 11.5, color: MUTED(68) }}>{name}</div>
            <Tag variant={signatoryName ? 'accent' : 'neutral'}>
                {signatoryName ? 'Signed' : 'Pending…'}
            </Tag>
        </Panel>
    );
}

/** One rail of the timeline card. */
function PhaseRow({
    index,
    heading,
    children,
}: {
    index: number;
    heading: string;
    children: ReactNode;
}) {
    return (
        <div style={{ display: 'flex', gap: 8 }}>
            <span
                style={{
                    width: 3,
                    flex: 'none',
                    background: PHASE_COLOURS[index % PHASE_COLOURS.length],
                    borderRadius: 2,
                }}
            />
            <div style={{ minWidth: 0, flex: 1 }}>
                <div style={{ color: 'var(--color-text)' }}>{heading}</div>
                {children}
            </div>
        </div>
    );
}

function CardHeading({ icon, label }: { icon: ReactNode; label: string }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <span style={{ color: 'var(--color-accent)', display: 'flex' }}>
                {icon}
            </span>
            <span style={{ fontFamily: 'var(--font-heading)', fontSize: 15 }}>
                {label}
            </span>
        </div>
    );
}

function TermField({
    label,
    value,
    error,
    onChange,
}: {
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="field">
            <label>{label}</label>
            <Textarea
                aria-label={label}
                value={value}
                maxLength={5000}
                onChange={(event) => onChange(event.target.value)}
            />
            <InputError message={error} className="mt-1 text-[11px]" />
        </div>
    );
}
