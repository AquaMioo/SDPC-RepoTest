import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    GithubLogoIcon,
    LinkSimpleIcon,
    StarIcon,
    UserIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';
import { Panel, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { Button } from '@/components/ui/button';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { store as messagesStore } from '@/routes/messages';
import { show as projectsShow } from '@/routes/projects';
import { store as invitationsStore } from '@/routes/projects/invitations';

type Props = {
    student: {
        id: number;
        name: string;
        headline: string | null;
        biography: string | null;
        school: string | null;
        course: string | null;
        yearLevel: number | null;
        githubUrl: string | null;
        portfolioUrl: string | null;
        isAvailable: boolean;
        rating: number;
        completedProjects: number;
        skills: { name: string; type: string }[];
    };
    existingApplications: {
        projectId: number;
        projectSlug: string;
        projectTitle: string;
        statusLabel: string;
        isAccepted: boolean;
    }[];
    invitableProjects: { id: number; slug: string; title: string }[];
    canInvite: boolean;
};

export default function StudentProfile({
    student,
    existingApplications,
    invitableProjects,
    canInvite,
}: Props) {
    const team = useCurrentTeam();
    const [projectId, setProjectId] = useState(
        invitableProjects[0]?.id.toString() ?? '',
    );

    const invite = useForm({ user_id: student.id });

    /** Puts the student on the posting's applicant list as an invitation. */
    const sendInvitation = () => {
        const project = invitableProjects.find(
            (candidate) => candidate.id.toString() === projectId,
        );

        if (project === undefined) {
            return;
        }

        invite.post(
            invitationsStore.url({
                current_team: team.slug,
                project: project.slug,
            }),
            { preserveScroll: true },
        );
    };

    /** Opens the thread for this student and one of your postings. */
    const message = (project: number) =>
        router.post(messagesStore.url(team.slug), {
            project_id: project,
            user_id: student.id,
        });

    return (
        <>
            <Head title={student.name} />

            <div className="mx-auto grid max-w-[1060px] items-start gap-6 px-8 pt-6 pb-[72px] lg:grid-cols-[minmax(0,1fr)_300px]">
                <div className="flex min-w-0 flex-col gap-4">
                    <Panel padding="lg" gap="lg">
                        <div className="flex items-center gap-4">
                            <span className="grid size-16 shrink-0 place-items-center rounded-full bg-primary/15 text-3xl text-primary">
                                <UserIcon />
                            </span>
                            <div className="mr-auto min-w-0">
                                <h3 className="m-0">{student.name}</h3>
                                <div className="text-[13px] text-muted-foreground">
                                    {[
                                        student.headline,
                                        student.course,
                                        student.yearLevel
                                            ? `Year ${student.yearLevel}`
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </div>
                            </div>
                            <Tag
                                variant={
                                    student.isAvailable ? 'accent' : 'neutral'
                                }
                            >
                                {student.isAvailable
                                    ? 'Available'
                                    : 'Unavailable'}
                            </Tag>
                        </div>

                        {student.biography && (
                            <p className="m-0 text-[13.5px] leading-relaxed opacity-85">
                                {student.biography}
                            </p>
                        )}

                        {student.school && (
                            <div className="text-[12.5px] text-muted-foreground">
                                {student.school}
                            </div>
                        )}
                    </Panel>

                    {student.skills.length > 0 && (
                        <Panel padding="lg" gap="lg">
                            <h6 className="m-0">Skills</h6>
                            <div className="flex flex-wrap gap-1.5">
                                {student.skills.map((skill) => (
                                    <Tag key={skill.name} variant="accent">
                                        {skill.name}
                                    </Tag>
                                ))}
                            </div>
                        </Panel>
                    )}

                    {existingApplications.length > 0 && (
                        <Panel padding="lg" gap="lg">
                            <h6 className="m-0">With your projects</h6>
                            <div className="flex flex-col gap-2.5">
                                {existingApplications.map((application) => (
                                    <div
                                        key={application.projectSlug}
                                        className="flex items-center gap-3"
                                    >
                                        <Link
                                            href={projectsShow.url({
                                                current_team: team.slug,
                                                project:
                                                    application.projectSlug,
                                            })}
                                            className="mr-auto text-[13.5px] hover:underline"
                                        >
                                            {application.projectTitle}
                                        </Link>
                                        <Tag
                                            variant={
                                                application.isAccepted
                                                    ? 'accent'
                                                    : 'neutral'
                                            }
                                        >
                                            {application.statusLabel}
                                        </Tag>
                                        {/* The invitation is the introduction,
                                            so a thread can open from here. */}
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                message(application.projectId)
                                            }
                                        >
                                            Message
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </Panel>
                    )}

                    <Panel padding="lg" gap="lg">
                        <div>
                            <h6 className="m-0">Invite to a project</h6>
                            <p className="m-0 text-[12.5px] text-muted-foreground">
                                An invitation puts {student.name} on the
                                posting's applicant list and opens a thread you
                                can message them in.
                            </p>
                        </div>

                        {!canInvite ? (
                            <p className="m-0 text-[12.5px] text-muted-foreground">
                                Your business needs to be verified before you
                                can invite students.
                            </p>
                        ) : invitableProjects.length === 0 ? (
                            <p className="m-0 text-[12.5px] text-muted-foreground">
                                {existingApplications.length > 0
                                    ? 'You have invited them to every posting you have open.'
                                    : 'You have no open postings to invite them to yet.'}
                            </p>
                        ) : (
                            <div className="flex flex-wrap items-center gap-2">
                                <select
                                    className="input"
                                    value={projectId}
                                    onChange={(e) =>
                                        setProjectId(e.target.value)
                                    }
                                >
                                    {invitableProjects.map((project) => (
                                        <option
                                            key={project.id}
                                            value={project.id}
                                        >
                                            {project.title}
                                        </option>
                                    ))}
                                </select>
                                <Button
                                    disabled={invite.processing}
                                    onClick={sendInvitation}
                                >
                                    {invite.processing
                                        ? 'Inviting…'
                                        : 'Send invitation'}
                                </Button>
                            </div>
                        )}

                        {invite.errors.user_id && (
                            <p className="m-0 text-[12.5px] text-destructive">
                                {invite.errors.user_id}
                            </p>
                        )}
                    </Panel>
                </div>

                <aside className="sticky top-[88px] flex flex-col gap-4">
                    <Panel padding="lg" gap="lg">
                        <PanelKicker>Track record</PanelKicker>
                        <div className="flex items-baseline gap-2.5">
                            <span className="font-heading text-[32px] leading-none">
                                {student.rating}
                            </span>
                            <StarIcon weight="fill" className="text-primary" />
                        </div>
                        <div className="text-[12px] text-muted-foreground">
                            {student.completedProjects} completed project
                            {student.completedProjects === 1 ? '' : 's'}
                        </div>
                    </Panel>

                    {(student.githubUrl || student.portfolioUrl) && (
                        <Panel padding="lg" gap="sm">
                            <PanelKicker>Links</PanelKicker>
                            {student.githubUrl && (
                                <a
                                    href={student.githubUrl}
                                    target="_blank"
                                    rel="noreferrer noopener"
                                    className="flex items-center gap-2 text-[13px] hover:underline"
                                >
                                    <GithubLogoIcon />
                                    GitHub
                                </a>
                            )}
                            {student.portfolioUrl && (
                                <a
                                    href={student.portfolioUrl}
                                    target="_blank"
                                    rel="noreferrer noopener"
                                    className="flex items-center gap-2 text-[13px] hover:underline"
                                >
                                    <LinkSimpleIcon />
                                    Portfolio
                                </a>
                            )}
                        </Panel>
                    )}
                </aside>
            </div>
        </>
    );
}
