import { Head, Link, router } from '@inertiajs/react';

import { Btn } from '@/components/sdpc/btn';
import { Panel, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { withdraw as applicationWithdraw } from '@/routes/student/applications';
import { index as boardIndex, show as boardShow } from '@/routes/student/board';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/** Which tag colour each application status wears. */
const STATUS_VARIANT: Record<string, 'accent' | 'neutral' | 'outline'> = {
    accepted: 'accent',
    shortlisted: 'outline',
    pending: 'outline',
    rejected: 'neutral',
    withdrawn: 'neutral',
};

type Props = {
    projects: {
        slug: string;
        title: string;
        client: string;
        status: string;
    }[];
    applications: {
        id: number;
        projectTitle: string;
        projectSlug: string;
        client: string;
        status: string;
        statusLabel: string;
        source: string;
        appliedAt: string | null;
        respondedAt: string | null;
        canWithdraw: boolean;
    }[];
};

/**
 * "Workflow" — the student's active builds and the applications behind them.
 */
export default function StudentWorkflow({ projects, applications }: Props) {
    const team = useCurrentTeam();

    return (
        <>
            <Head title="Workflow" />

            <div
                style={{
                    maxWidth: 1320,
                    margin: '0 auto',
                    padding: '30px 32px 72px',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 22,
                }}
            >
                <div
                    style={{ display: 'flex', alignItems: 'flex-end', gap: 16 }}
                >
                    <div style={{ marginRight: 'auto' }}>
                        <h3 style={{ margin: 0 }}>Workflow</h3>
                        <div style={{ fontSize: 13, color: MUTED(60) }}>
                            What you are building, and what you are waiting on.
                        </div>
                    </div>
                    <Btn asChild variant="primary">
                        <Link href={boardIndex.url(team.slug)}>
                            Find more work
                        </Link>
                    </Btn>
                </div>

                <section
                    style={{ display: 'flex', flexDirection: 'column', gap: 14 }}
                >
                    <span style={{ fontSize: 13 }}>Active projects</span>

                    {projects.length === 0 ? (
                        <Panel padding="lg" gap="sm">
                            <span style={{ fontSize: 13 }}>
                                No active project yet.
                            </span>
                            <span style={{ fontSize: 12.5, color: MUTED(65) }}>
                                Once a client accepts your application, the
                                build shows up here.
                            </span>
                        </Panel>
                    ) : (
                        projects.map((project) => (
                            <Panel key={project.slug} padding="lg" gap="lg">
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'flex-start',
                                        gap: 12,
                                    }}
                                >
                                    <div style={{ marginRight: 'auto' }}>
                                        <div
                                            style={{
                                                fontFamily:
                                                    'var(--font-heading)',
                                                fontSize: 16,
                                            }}
                                        >
                                            {project.title}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 12,
                                                color: MUTED(65),
                                            }}
                                        >
                                            {project.client}
                                        </div>
                                    </div>
                                    <Tag variant="accent">{project.status}</Tag>
                                </div>

                            </Panel>
                        ))
                    )}
                </section>

                <section
                    style={{ display: 'flex', flexDirection: 'column', gap: 14 }}
                >
                    <span style={{ fontSize: 13 }}>Your applications</span>

                    {applications.length === 0 ? (
                        <Panel padding="lg" gap="sm">
                            <span style={{ fontSize: 13 }}>
                                You have not applied to anything yet.
                            </span>
                            <Btn
                                asChild
                                variant="secondary"
                                style={{ alignSelf: 'start' }}
                            >
                                <Link href={boardIndex.url(team.slug)}>
                                    Browse postings
                                </Link>
                            </Btn>
                        </Panel>
                    ) : (
                        <Panel padding="lg" gap="md">
                            <PanelKicker>
                                {applications.length} in total
                            </PanelKicker>

                            {applications.map((application) => (
                                <div
                                    key={application.id}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 12,
                                        paddingTop: 10,
                                        borderTop:
                                            '1px solid var(--color-divider)',
                                    }}
                                >
                                    <div
                                        style={{
                                            marginRight: 'auto',
                                            minWidth: 0,
                                        }}
                                    >
                                        <Link
                                            href={boardShow.url({
                                                current_team: team.slug,
                                                project: application.projectSlug,
                                            })}
                                            style={{
                                                fontSize: 13.5,
                                                color: 'var(--color-text)',
                                                textDecoration: 'none',
                                            }}
                                        >
                                            {application.projectTitle}
                                        </Link>
                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                color: MUTED(65),
                                            }}
                                        >
                                            {application.client} ·{' '}
                                            {application.source} ·{' '}
                                            {application.appliedAt}
                                        </div>
                                    </div>

                                    <Tag
                                        variant={
                                            STATUS_VARIANT[
                                                application.status
                                            ] ?? 'neutral'
                                        }
                                    >
                                        {application.statusLabel}
                                    </Tag>

                                    {application.canWithdraw && (
                                        <Btn
                                            variant="ghost"
                                            onClick={() =>
                                                router.delete(
                                                    applicationWithdraw.url({
                                                        current_team: team.slug,
                                                        application:
                                                            application.id,
                                                    }),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Withdraw
                                        </Btn>
                                    )}
                                </div>
                            ))}
                        </Panel>
                    )}
                </section>
            </div>
        </>
    );
}
