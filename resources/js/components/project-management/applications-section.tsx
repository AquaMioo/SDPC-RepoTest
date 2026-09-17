import { Link, router, usePage } from '@inertiajs/react';

import type { Application } from '@/components/project-management/types';
import { Btn } from '@/components/sdpc/btn';
import { Panel, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { store as messagesStore } from '@/routes/messages';
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

/**
 * The student's applications, carried over from the old Workflow screen.
 *
 * Unchanged in what it does — message, accept or decline an invitation,
 * withdraw an undecided application — it is simply a section of Project
 * Management now, and the main content until the student is collaborating.
 */
export function ApplicationsSection({
    applications,
    teamSlug,
}: {
    applications: Application[];
    teamSlug: string;
}) {
    const { auth } = usePage().props;

    /*
     * Open the thread for one posting. The application behind it is what makes
     * this allowed, and the student is one of its two sides — so the student's
     * own id goes over, not the client's.
     */
    const message = (projectId: number) =>
        router.post(messagesStore.url(teamSlug), {
            project_id: projectId,
            user_id: auth.user.id,
        });

    return (
        <section style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
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
                        <Link href={boardIndex.url(teamSlug)}>
                            Browse postings
                        </Link>
                    </Btn>
                </Panel>
            ) : (
                <Panel padding="lg" gap="md">
                    <PanelKicker>{applications.length} in total</PanelKicker>

                    {applications.map((application) => (
                        <div
                            key={application.id}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                flexWrap: 'wrap',
                                gap: 12,
                                paddingTop: 10,
                                borderTop: '1px solid var(--color-divider)',
                            }}
                        >
                            <div style={{ marginRight: 'auto', minWidth: 0 }}>
                                <Link
                                    href={boardShow.url({
                                        current_team: teamSlug,
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
                                    style={{ fontSize: 11.5, color: MUTED(65) }}
                                >
                                    {application.client} · {application.source}{' '}
                                    · {application.appliedAt}
                                </div>
                            </div>

                            <Tag
                                variant={
                                    STATUS_VARIANT[application.status] ??
                                    'neutral'
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
                             * A client who invites has already said yes, so
                             * this row is waiting on the student rather than
                             * on the business.
                             */}
                            {application.awaitsMyDecision && (
                                <>
                                    {/*
                                     * One project at a time. Accepting one
                                     * closes the rest, so this only shows for
                                     * an invitation that arrived afterwards.
                                     */}
                                    {application.canAccept ? (
                                        <Btn
                                            variant="primary"
                                            onClick={() =>
                                                router.post(
                                                    applicationAccept.url({
                                                        current_team: teamSlug,
                                                        application:
                                                            application.id,
                                                    }),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Accept
                                        </Btn>
                                    ) : (
                                        <span
                                            style={{
                                                fontSize: 11.5,
                                                color: MUTED(65),
                                                maxWidth: 220,
                                            }}
                                        >
                                            You are already on a project, so
                                            this one cannot be accepted.
                                        </span>
                                    )}
                                    <Btn
                                        variant="ghost"
                                        onClick={() =>
                                            router.post(
                                                applicationDecline.url({
                                                    current_team: teamSlug,
                                                    application: application.id,
                                                }),
                                                {},
                                                { preserveScroll: true },
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
                                                current_team: teamSlug,
                                                application: application.id,
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
    );
}
