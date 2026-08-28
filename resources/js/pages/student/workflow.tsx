import { Head, Link, router, usePage } from '@inertiajs/react';

import { Btn } from '@/components/sdpc/btn';
import { Panel, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { store as messagesStore } from '@/routes/messages';
import { process as studentProcess } from '@/routes/student';
import {
    accept as applicationAccept,
    decline as applicationDecline,
    withdraw as applicationWithdraw,
} from '@/routes/student/applications';
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
        id: number;
        slug: string;
        title: string;
        client: string;
        status: string;
    }[];
    applications: {
        id: number;
        projectId: number;
        projectTitle: string;
        projectSlug: string;
        client: string;
        status: string;
        statusLabel: string;
        source: string;
        appliedAt: string | null;
        respondedAt: string | null;
        awaitsMyDecision: boolean;
        canWithdraw: boolean;
        canMessage: boolean;
    }[];
};

/**
 * "Workflow" — the student's active builds and the applications behind them.
 */
export default function StudentWorkflow({ projects, applications }: Props) {
    const team = useCurrentTeam();
    const { auth } = usePage().props;

    /*
     * Open the thread for one posting. The application behind it is what makes
     * this allowed, and the student is one of its two sides — so the student's
     * own id goes over, not the client's.
     */
    const message = (projectId: number) =>
        router.post(messagesStore.url(team.slug), {
            project_id: projectId,
            user_id: auth.user.id,
        });

    return (
        <>
            <Head title="Workflow" />

            <div
                style={{
                    maxWidth: 1320,
                    margin: '0 auto',
                    padding: '30px clamp(16px, 4vw, 32px) 72px',
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
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 14,
                    }}
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

                                <div style={{ display: 'flex', gap: 8 }}>
                                    {/* Progress lives on the agreement's
                                        milestones, so the detail is there. */}
                                    <Btn asChild>
                                        <Link
                                            href={studentProcess.url(team.slug)}
                                        >
                                            Track progress
                                        </Link>
                                    </Btn>
                                    <Btn
                                        variant="secondary"
                                        onClick={() => message(project.id)}
                                    >
                                        Message client
                                    </Btn>
                                </div>
                            </Panel>
                        ))
                    )}
                </section>

                <section
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 14,
                    }}
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
                                                project:
                                                    application.projectSlug,
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

                                    {application.canMessage && (
                                        <Btn
                                            variant="ghost"
                                            onClick={() =>
                                                message(application.projectId)
                                            }
                                        >
                                            Message
                                        </Btn>
                                    )}

                                    {/*
                                     * A client who invites has already said
                                     * yes, so this row is waiting on the
                                     * student rather than on the business.
                                     */}
                                    {application.awaitsMyDecision && (
                                        <>
                                            <Btn
                                                variant="primary"
                                                onClick={() =>
                                                    router.post(
                                                        applicationAccept.url({
                                                            current_team:
                                                                team.slug,
                                                            application:
                                                                application.id,
                                                        }),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Accept
                                            </Btn>
                                            <Btn
                                                variant="ghost"
                                                onClick={() =>
                                                    router.post(
                                                        applicationDecline.url({
                                                            current_team:
                                                                team.slug,
                                                            application:
                                                                application.id,
                                                        }),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Decline
                                            </Btn>
                                        </>
                                    )}

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
