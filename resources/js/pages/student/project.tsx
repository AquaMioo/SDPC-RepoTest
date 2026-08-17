import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeftIcon, CheckCircleIcon } from '@phosphor-icons/react';

import { Btn } from '@/components/sdpc/btn';
import Field from '@/components/sdpc/field';
import { Panel, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { Input } from '@/components/ui/input';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { apply as boardApply, index as boardIndex } from '@/routes/student/board';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

type Props = {
    project: {
        slug: string;
        title: string;
        category: string;
        industry: string | null;
        client: string;
        skills: string[];
        applicants: number;
        isAcceptingApplications: boolean;
        hasApplied: boolean;
        description: string;
        objectives: string | null;
        businessDescription: string | null;
        city: string | null;
    };
    application: {
        status: string;
        statusLabel: string;
        appliedAt: string | null;
    } | null;
    canApply: boolean;
    /** One student, one build — true while they already have work. */
    holdsProjectInHand: boolean;
};

/**
 * One posting in full, with the form a student applies through.
 */
export default function StudentProject({
    project,
    application,
    canApply,
    holdsProjectInHand,
}: Props) {
    const team = useCurrentTeam();

    const form = useForm({ cover_letter: '', proposed_rate: '' });

    const isOpen = project.isAcceptingApplications && application === null;

    return (
        <>
            <Head title={project.title} />

            <div
                style={{
                    maxWidth: 1060,
                    margin: '0 auto',
                    padding: '24px 32px 72px',
                    display: 'grid',
                    gridTemplateColumns: 'minmax(0,1fr) 320px',
                    gap: 22,
                    alignItems: 'start',
                }}
            >
                <div
                    style={{ display: 'flex', flexDirection: 'column', gap: 16 }}
                >
                    <Btn asChild variant="ghost" style={{ alignSelf: 'start' }}>
                        <Link href={boardIndex.url(team.slug)}>
                            <ArrowLeftIcon />
                            Back to the board
                        </Link>
                    </Btn>

                    <div>
                        <h3 style={{ margin: 0 }}>{project.title}</h3>
                        <div style={{ fontSize: 13, color: MUTED(60) }}>
                            {project.client}
                            {project.city ? ` · ${project.city}` : ''} ·{' '}
                            {project.category}
                        </div>
                    </div>

                    <Panel padding="lg" gap="md">
                        <PanelKicker>The work</PanelKicker>
                        <p
                            style={{
                                margin: 0,
                                fontSize: 13,
                                lineHeight: 1.6,
                                whiteSpace: 'pre-line',
                            }}
                        >
                            {project.description}
                        </p>

                        {project.objectives && (
                            <>
                                <PanelKicker>Objectives</PanelKicker>
                                <p
                                    style={{
                                        margin: 0,
                                        fontSize: 13,
                                        lineHeight: 1.6,
                                        whiteSpace: 'pre-line',
                                        color: MUTED(78),
                                    }}
                                >
                                    {project.objectives}
                                </p>
                            </>
                        )}

                        {project.skills.length > 0 && (
                            <div
                                style={{
                                    display: 'flex',
                                    flexWrap: 'wrap',
                                    gap: 5,
                                }}
                            >
                                {project.skills.map((skill) => (
                                    <Tag key={skill} variant="outline">
                                        {skill}
                                    </Tag>
                                ))}
                            </div>
                        )}
                    </Panel>

                    {isOpen && canApply && !holdsProjectInHand && (
                        <Panel padding="lg" gap="lg">
                            <PanelKicker>Apply</PanelKicker>

                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    form.post(
                                        boardApply.url({
                                            current_team: team.slug,
                                            project: project.slug,
                                        }),
                                        { preserveScroll: true },
                                    );
                                }}
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 14,
                                }}
                            >
                                <Field
                                    label="Why you are a fit"
                                    error={
                                        form.errors.cover_letter ??
                                        // @ts-expect-error server-side guard key
                                        form.errors.application
                                    }
                                    required
                                >
                                    {(props) => (
                                        <textarea
                                            {...props}
                                            className="min-h-[130px] rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            maxLength={2000}
                                            placeholder="What you have built before, and how you would approach this."
                                            value={form.data.cover_letter}
                                            onChange={(e) =>
                                                form.setData(
                                                    'cover_letter',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>

                                <Field
                                    label="Your rate per hour (optional)"
                                    error={form.errors.proposed_rate}
                                >
                                    {(props) => (
                                        <Input
                                            {...props}
                                            type="number"
                                            min={0}
                                            placeholder="250"
                                            value={form.data.proposed_rate}
                                            onChange={(e) =>
                                                form.setData(
                                                    'proposed_rate',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>

                                <Btn
                                    variant="primary"
                                    type="submit"
                                    disabled={form.processing}
                                    style={{ alignSelf: 'start' }}
                                >
                                    {form.processing
                                        ? 'Sending…'
                                        : 'Send application'}
                                </Btn>
                            </form>
                        </Panel>
                    )}
                </div>

                <aside
                    style={{
                        position: 'sticky',
                        top: 88,
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 14,
                    }}
                >
                    <Panel padding="lg" gap="md">
                        {application !== null ? (
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                }}
                            >
                                <CheckCircleIcon
                                    style={{ color: 'var(--color-accent)' }}
                                />
                                <span style={{ fontSize: 13 }}>
                                    Applied {application.appliedAt}
                                </span>
                                <Tag variant="accent">
                                    {application.statusLabel}
                                </Tag>
                            </div>
                        ) : !project.isAcceptingApplications ? (
                            <span style={{ fontSize: 12.5, color: MUTED(70) }}>
                                This posting is not taking applications.
                            </span>
                        ) : !canApply ? (
                            <span style={{ fontSize: 12.5, color: MUTED(70) }}>
                                Applying waits until an administrator has
                                verified your student credential.
                            </span>
                        ) : holdsProjectInHand ? (
                            <span style={{ fontSize: 12.5, color: MUTED(70) }}>
                                You already have a project in hand. Finish it
                                before taking on another.
                            </span>
                        ) : (
                            <span style={{ fontSize: 12.5, color: MUTED(70) }}>
                                {project.applicants} student
                                {project.applicants === 1 ? '' : 's'} have
                                applied so far.
                            </span>
                        )}
                    </Panel>


                    {project.businessDescription && (
                        <Panel padding="lg" gap="sm">
                            <PanelKicker>About {project.client}</PanelKicker>
                            <p
                                style={{
                                    margin: 0,
                                    fontSize: 12.5,
                                    lineHeight: 1.55,
                                    color: MUTED(75),
                                }}
                            >
                                {project.businessDescription}
                            </p>
                        </Panel>
                    )}
                </aside>
            </div>
        </>
    );
}

