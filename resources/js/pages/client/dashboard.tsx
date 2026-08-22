import { Head, Link } from '@inertiajs/react';
import { PlusIcon } from '@phosphor-icons/react';
import { useState } from 'react';

import {
    AnnouncementsPanel,
    CalendarPanel,
    ProjectProgressPanel,
    ProjectTeamPanel,
    type Announcement,
    type CalendarEvent,
    type CurrentProject,
    type TeamMemberCard,
} from '@/components/client/overview-panels';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Button } from '@/components/ui/button';
import { useCurrentTeam } from '@/hooks/use-current-team';
import {
    create as projectsCreate,
    show as projectsShow,
} from '@/routes/projects';
import type { DashboardInvitation } from '@/types';

type Props = {
    userName: string;
    pendingInvitations?: DashboardInvitation[];
    canPostProject: boolean;
    announcement: Announcement | null;
    currentProject?: CurrentProject | null;
    projectTeam?: TeamMemberCard[];
    calendarEvents?: CalendarEvent[];
};

/**
 * The client workspace overview.
 *
 * Three panels: calendar, progress, team. The counts, activity feed and
 * shortlist that used to sit here were taken off on purpose — applications are
 * read on Recruit and on the posting itself, which is where a client acts on
 * them.
 */
export default function ClientDashboard({
    userName,
    pendingInvitations = [],
    canPostProject,
    announcement,
    currentProject,
    projectTeam,
    calendarEvents,
}: Props) {
    const team = useCurrentTeam();
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );

    // Both the progress panel and the team panel point at the running posting,
    // and neither has anywhere to go before one exists.
    const projectHref = currentProject
        ? projectsShow.url([team.slug, currentProject.slug])
        : null;

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />

            <div className="mx-auto max-w-[1320px] px-8 pt-[30px] pb-[72px]">
                <div className="mb-6 flex items-start gap-5">
                    <div className="mr-auto">
                        <h3 className="m-0 text-[22px]">Welcome, {userName}</h3>
                        <div className="text-[13.5px] text-muted-foreground">
                            Here's what's happening with your projects today.
                        </div>
                    </div>
                    {/* Hidden while the team's one posting slot is taken. */}
                    {canPostProject && (
                        <Button asChild variant="outline">
                            <Link href={projectsCreate.url(team.slug)}>
                                <PlusIcon />
                                Post a Job
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.2fr)_minmax(0,0.95fr)]">
                    <CalendarPanel events={calendarEvents} />
                    <ProjectProgressPanel
                        project={currentProject}
                        href={projectHref}
                    />
                    <ProjectTeamPanel
                        members={projectTeam}
                        workspaceHref={projectHref}
                    />
                </div>

                <div className="mt-5">
                    <AnnouncementsPanel announcement={announcement} />
                </div>
            </div>
        </>
    );
}
