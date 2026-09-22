import { Head, Link, usePage } from '@inertiajs/react';
import { Eye, LogOut, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import CreateTeamModal from '@/components/create-team-modal';
import Heading from '@/components/heading';
import LeaveTeamModal from '@/components/leave-team-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { edit, index } from '@/routes/teams';
import type { Team } from '@/types';

/** A student team the signed-in client is under contract with. */
type CollaboratingTeam = {
    id: number;
    name: string;
    slug: string;
    student: string;
    members: string[];
};

type Props = {
    teams: Team[];
    /**
     * False for a client (their team is the business) and for a student who
     * already leads one (a student leads one group).
     */
    canCreateTeam?: boolean;
    /** Why the button is absent, shown in its place. */
    createBlockedBecause?: string | null;
    collaboratingTeams?: CollaboratingTeam[];
    /** How a student is on their one team. Null for a client. */
    membership?: {
        kind: 'created' | 'joined';
        team: string;
        lead: string | null;
        memberCount: number;
    } | null;
    /** Teams on a client's build: nobody leaves until the client completes it. */
    buildingTeamIds?: number[];
};

export default function TeamsIndex({
    teams,
    canCreateTeam = true,
    createBlockedBecause = null,
    collaboratingTeams = [],
    membership = null,
    buildingTeamIds = [],
}: Props) {
    /*
     * A client administers no team. Theirs is the business they registered
     * as — it holds only them and owns the postings — so renaming it,
     * inviting into it and deleting it are all closed to them by
     * HasTeams::hasTeamPermission(). The row's pencil offered an edit the
     * server would refuse, and the page behind it had nothing on it for a
     * client to read, so the whole action is dropped rather than softened
     * to an eye. Read from the shared auth props, the way SettingsShell
     * picks its chrome.
     */
    const role = usePage<{ auth?: { role?: string | null } }>().props.auth
        ?.role;
    const isClient = role === 'client';

    const [leaveTeamDialogOpen, setLeaveTeamDialogOpen] = useState(false);
    const [teamLeaving, setTeamLeaving] = useState<Team | null>(null);

    const openLeaveTeamDialog = (team: Team) => {
        setTeamLeaving(team);
        setLeaveTeamDialogOpen(true);
    };

    return (
        <>
            <Head title="Teams" />

            <h1 className="sr-only">Teams</h1>

            {/*
             * Its own gutter. This page used to sit inside SettingsLayout,
             * which supplied the shell; it is reached from the header now, so
             * it carries one itself or renders flush against the window edge.
             * Same clamp formula as every other screen — see .ai/rules/pages.md.
             */}
            <div
                className="page-shell flex flex-col space-y-6"
                style={{
                    maxWidth: 'clamp(1320px, 100vw - 320px, 1600px)',
                    paddingTop: 30,
                    paddingBottom: 72,
                }}
            >
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Team"
                        description="Your team and who is in it"
                    />

                    {canCreateTeam ? (
                        <CreateTeamModal>
                            <Button data-test="teams-new-team-button">
                                <Plus /> New team
                            </Button>
                        </CreateTeamModal>
                    ) : (
                        /*
                         * Says why instead of leaving a gap. The rule is
                         * enforced server-side either way; without this the
                         * page just quietly lacked a button somebody had seen
                         * there a moment ago.
                         */
                        createBlockedBecause && (
                            <p className="max-w-xs text-right text-sm text-muted-foreground">
                                {createBlockedBecause}
                            </p>
                        )
                    )}
                </div>

                {membership && (
                    <div
                        data-test="team-membership-status"
                        className="rounded-lg border p-4"
                    >
                        <p className="font-medium">
                            {membership.kind === 'joined'
                                ? "You've joined the team"
                                : "You've created the team"}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {membership.kind === 'joined'
                                ? `You're on ${membership.team}${membership.lead ? `, led by ${membership.lead}` : ''}. Your old team was removed when you joined, since you can only be on one team at a time.`
                                : membership.memberCount > 1
                                  ? `You lead ${membership.team}, and ${membership.memberCount - 1} ${membership.memberCount - 1 === 1 ? 'person has' : 'people have'} joined you. You can't join another team while you lead this one.`
                                  : `${membership.team} is yours. Invite others to join you, or accept an invite to someone else's team. If you do, it replaces this one.`}
                        </p>
                    </div>
                )}

                <div className="space-y-3">
                    {teams.map((team) => {
                        /*
                         * Anyone but the lead may leave. A student who leaves
                         * the team they joined is given one of their own
                         * again, so is_personal no longer decides this.
                         */
                        const isBuilding = buildingTeamIds.includes(team.id);
                        const canLeaveTeam =
                            team.role !== 'owner' && !isBuilding;

                        /*
                         * Neither action belongs on a client's row: the team
                         * page behind both is empty for them, and the pencil
                         * offered an edit hasTeamPermission() refuses.
                         */
                        const canViewTeam = !isClient && team.role === 'member';
                        const canEditTeam = !isClient && team.role !== 'member';

                        return (
                            <div
                                key={team.id}
                                data-test="team-row"
                                className="flex items-center justify-between gap-4 rounded-lg border p-4"
                            >
                                <div className="flex items-center gap-4">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">
                                                {team.name}
                                            </span>
                                            {/*
                                             * How many people are in it, not
                                             * where it came from. "Personal"
                                             * read as a category and stopped
                                             * being true the moment somebody
                                             * was invited in.
                                             */}
                                            {typeof team.memberCount ===
                                            'number' ? (
                                                <Badge variant="secondary">
                                                    {team.memberCount === 1
                                                        ? 'Just you'
                                                        : `${team.memberCount} members`}
                                                </Badge>
                                            ) : null}
                                        </div>
                                        <span className="text-sm text-muted-foreground">
                                            {team.roleLabel}
                                            {isBuilding &&
                                                team.role !== 'owner' &&
                                                ' · On a project — you can leave once the client completes it'}
                                        </span>
                                    </div>
                                </div>

                                {/*
                                 * Nothing to offer a client here: they cannot
                                 * leave their own team and they cannot edit
                                 * it. The cluster is left out entirely so the
                                 * row does not carry a gap where the buttons
                                 * used to be.
                                 */}
                                {canLeaveTeam || canViewTeam || canEditTeam ? (
                                    <TooltipProvider>
                                        <div className="flex items-center gap-2">
                                            {canLeaveTeam ? (
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            data-test="team-leave-button"
                                                            onClick={() =>
                                                                openLeaveTeamDialog(
                                                                    team,
                                                                )
                                                            }
                                                        >
                                                            <LogOut className="h-4 w-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>Leave team</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            ) : null}

                                            {canViewTeam ? (
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            data-test="team-view-button"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={edit(
                                                                    team.slug,
                                                                )}
                                                            >
                                                                <Eye className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>View team</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            ) : canEditTeam ? (
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            data-test="team-edit-button"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={edit(
                                                                    team.slug,
                                                                )}
                                                            >
                                                                <Pencil className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>Edit team</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            ) : null}
                                        </div>
                                    </TooltipProvider>
                                ) : null}
                            </div>
                        );
                    })}

                    {teams.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            You don't belong to any teams yet.
                        </p>
                    ) : null}
                </div>

                {/*
                 * The other side of the work. Read-only on purpose: this is
                 * somebody else's team, shown because a signed agreement makes
                 * the client entitled to know who is building for them — not
                 * because they have any say over it.
                 */}
                {collaboratingTeams.length > 0 ? (
                    <div className="space-y-3">
                        <Heading
                            variant="small"
                            title="Working with you"
                            description="The student teams building your projects"
                        />

                        {collaboratingTeams.map((team) => (
                            <div
                                key={team.id}
                                data-test="collaborating-team-row"
                                className="rounded-lg border p-4"
                            >
                                <div className="flex items-center gap-2">
                                    <span className="font-medium">
                                        {team.name}
                                    </span>
                                    <Badge variant="secondary">
                                        Under contract
                                    </Badge>
                                </div>

                                <span className="text-sm text-muted-foreground">
                                    {team.student}
                                </span>

                                {team.members.length > 0 ? (
                                    <div className="mt-2 text-sm text-muted-foreground">
                                        {team.members.join(' · ')}
                                    </div>
                                ) : null}
                            </div>
                        ))}
                    </div>
                ) : null}
            </div>

            <LeaveTeamModal
                team={teamLeaving}
                open={leaveTeamDialogOpen}
                onOpenChange={setLeaveTeamDialogOpen}
            />
        </>
    );
}

TeamsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Teams',
            href: index(),
        },
    ],
};
