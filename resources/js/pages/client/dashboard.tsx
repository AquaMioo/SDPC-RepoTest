import { Head, Link } from '@inertiajs/react';
import { PlusIcon } from '@phosphor-icons/react';
import { useState } from 'react';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import Meter from '@/components/sdpc/meter';
import { Panel, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { edit as clientProfileEdit } from '@/routes/client-profile';
import {
    create as projectsCreate,
    show as projectsShow,
} from '@/routes/projects';
import type { DashboardInvitation } from '@/types';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    canPostProject: boolean;
    stats: {
        projectsPosted: number;
        activeProjects: number;
        completedProjects: number;
        pendingApplications: number;
        shortlistedStudents: number;
        acceptedStudents: number;
    };
    profileCompletion: number;
    recentActivity?: {
        id: number;
        studentName: string;
        projectTitle: string;
        projectSlug: string;
        statusLabel: string;
        happenedAt: string | null;
    }[];
    shortlistedStudents?: {
        id: number;
        studentName: string;
        headline: string | null;
        projectTitle: string;
    }[];
};

export default function ClientDashboard({
    pendingInvitations = [],
    canPostProject,
    stats,
    profileCompletion,
    recentActivity,
    shortlistedStudents,
}: Props) {
    const team = useCurrentTeam();
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );

    const cards = [
        { label: 'Projects posted', value: stats.projectsPosted },
        { label: 'Active projects', value: stats.activeProjects },
        { label: 'Completed', value: stats.completedProjects },
        { label: 'Pending applications', value: stats.pendingApplications },
        { label: 'Shortlisted', value: stats.shortlistedStudents },
        { label: 'Accepted students', value: stats.acceptedStudents },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />

            <div className="mx-auto max-w-[1320px] px-8 pt-[30px] pb-[72px]">
                <div className="mb-6 flex items-end gap-5">
                    <div className="mr-auto">
                        <h3 className="m-0">Welcome,</h3>
                        <div className="text-[15px] text-muted-foreground">
                            {team.name}
                        </div>
                    </div>
                    {/* Hidden while the team's one posting slot is taken. */}
                    {canPostProject && (
                        <Button asChild>
                            <Link href={projectsCreate.url(team.slug)}>
                                <PlusIcon />
                                Post a Job
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    {cards.map((card) => (
                        <Panel key={card.label} gap="sm">
                            <PanelKicker>{card.label}</PanelKicker>
                            <div className="font-heading text-[28px] leading-none">
                                {card.value}
                            </div>
                        </Panel>
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                    <Panel padding="lg" gap="lg">
                        <div className="flex items-center">
                            <h6 className="m-0 mr-auto">Recent activity</h6>
                        </div>

                        {recentActivity === undefined ? (
                            <div className="flex flex-col gap-2.5">
                                {[0, 1, 2, 3].map((row) => (
                                    <Skeleton
                                        key={row}
                                        className="h-11 w-full"
                                    />
                                ))}
                            </div>
                        ) : recentActivity.length === 0 ? (
                            <p className="text-[12.5px] text-muted-foreground">
                                No applications yet. They appear here as
                                students respond to your postings.
                            </p>
                        ) : (
                            <div className="flex flex-col gap-2.5">
                                {recentActivity.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex items-center gap-2.5"
                                    >
                                        <div className="mr-auto min-w-0">
                                            <div className="truncate text-[13.5px]">
                                                {item.studentName}
                                            </div>
                                            <Link
                                                href={projectsShow.url({
                                                    current_team: team.slug,
                                                    project: item.projectSlug,
                                                })}
                                                className="truncate text-[11.5px] text-muted-foreground hover:underline"
                                            >
                                                {item.projectTitle}
                                            </Link>
                                        </div>
                                        <Tag variant="neutral">
                                            {item.statusLabel}
                                        </Tag>
                                        <span className="w-24 shrink-0 text-right text-[11px] text-muted-foreground">
                                            {item.happenedAt}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Panel>

                    <div className="flex flex-col gap-4">
                        <Panel padding="lg" gap="lg">
                            <h6 className="m-0">Profile completion</h6>
                            <Meter
                                label="Business profile"
                                value={profileCompletion}
                            />
                            <Button
                                asChild
                                variant="secondary"
                                className="w-full"
                            >
                                <Link href={clientProfileEdit.url(team.slug)}>
                                    Complete your profile
                                </Link>
                            </Button>
                        </Panel>

                        <Panel padding="lg" gap="lg">
                            <PanelKicker>Shortlisted students</PanelKicker>

                            {shortlistedStudents === undefined ? (
                                <div className="flex flex-col gap-2">
                                    {[0, 1, 2].map((row) => (
                                        <Skeleton
                                            key={row}
                                            className="h-9 w-full"
                                        />
                                    ))}
                                </div>
                            ) : shortlistedStudents.length === 0 ? (
                                <p className="text-[12.5px] text-muted-foreground">
                                    Students you shortlist will collect here.
                                </p>
                            ) : (
                                <div className="flex flex-col gap-2.5">
                                    {shortlistedStudents.map((student) => (
                                        <div key={student.id}>
                                            <div className="text-[13.5px]">
                                                {student.studentName}
                                            </div>
                                            <div className="text-[11px] text-muted-foreground">
                                                {student.headline ??
                                                    student.projectTitle}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Panel>
                    </div>
                </div>
            </div>
        </>
    );
}
