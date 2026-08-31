import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    GithubLogoIcon,
    LinkSimpleIcon,
    StarIcon,
    UserIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';
import ReportAccountDialog from '@/components/report-account-dialog';
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
        avatarUrl: string | null;
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
        location: string | null;
        weeklyHours: number | null;
        availabilityNote: string | null;
        responseTimeHours: number | null;
        hourlyRate: number | null;
        educationNote: string | null;
        portfolio: {
            id: number;
            title: string;
            role: string | null;
            description: string | null;
            year: number | null;
            url: string | null;
            repositoryUrl: string | null;
            skills: string[];
        }[];
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
    reportCategories: { value: string; label: string }[];
};

export default function StudentProfile({
    student,
    existingApplications,
    invitableProjects,
    canInvite,
    reportCategories,
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

            <div className="mx-auto grid max-w-[clamp(1060px,100vw_-_320px,1600px)] items-start gap-6 px-4 pt-6 pb-[72px] sm:px-6 lg:grid-cols-[minmax(0,1fr)_300px] lg:px-8">
                <div className="flex min-w-0 flex-col gap-4">
                    <Panel padding="lg" gap="lg">
                        <div className="flex items-center gap-4">
                            <StudentAvatar url={student.avatarUrl} />
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

                        <div className="flex justify-end">
                            <ReportAccountDialog
                                userId={student.id}
                                userName={student.name}
                                categories={reportCategories}
                            />
                        </div>
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

                    {student.portfolio.length > 0 && (
                        <Panel padding="lg" gap="lg">
                            <div>
                                <h6 className="m-0">Featured portfolio</h6>
                                <p className="m-0 text-[12.5px] text-muted-foreground">
                                    Work {student.name} has already shipped.
                                </p>
                            </div>

                            <div className="flex flex-col gap-4">
                                {student.portfolio.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex flex-col gap-1.5 border-t border-[var(--color-divider)] pt-3 first:border-0 first:pt-0"
                                    >
                                        <div className="flex items-center gap-2">
                                            <span className="font-heading text-[16px]">
                                                {item.title}
                                            </span>
                                            {item.role && (
                                                <Tag variant="neutral">
                                                    {item.role}
                                                </Tag>
                                            )}
                                            {item.year && (
                                                <span className="text-[11.5px] text-muted-foreground">
                                                    {item.year}
                                                </span>
                                            )}
                                        </div>

                                        {item.description && (
                                            <p className="m-0 text-[12.5px] leading-relaxed text-muted-foreground">
                                                {item.description}
                                            </p>
                                        )}

                                        <div className="flex flex-wrap items-center gap-1.5">
                                            {item.skills.map((skill) => (
                                                <Tag
                                                    key={skill}
                                                    variant="outline"
                                                >
                                                    {skill}
                                                </Tag>
                                            ))}
                                            {item.repositoryUrl && (
                                                <a
                                                    href={item.repositoryUrl}
                                                    target="_blank"
                                                    rel="noreferrer noopener"
                                                    className="flex items-center gap-1 text-[12px] hover:underline"
                                                >
                                                    <GithubLogoIcon />
                                                    Repository
                                                </a>
                                            )}
                                            {item.url && (
                                                <a
                                                    href={item.url}
                                                    target="_blank"
                                                    rel="noreferrer noopener"
                                                    className="flex items-center gap-1 text-[12px] hover:underline"
                                                >
                                                    <LinkSimpleIcon />
                                                    Live
                                                </a>
                                            )}
                                        </div>
                                    </div>
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

                    <Panel padding="lg" gap="sm">
                        <PanelKicker>Availability</PanelKicker>
                        <div className="flex items-center gap-2 text-[12.5px]">
                            <span
                                className={`size-2 rounded-full ${student.isAvailable ? 'bg-primary' : 'bg-muted-foreground'}`}
                            />
                            {student.availabilityNote ??
                                (student.isAvailable
                                    ? 'Open to a project this term'
                                    : 'Not taking new work')}
                        </div>
                        <div className="text-[11.5px] text-muted-foreground">
                            {[
                                student.weeklyHours
                                    ? `≈ ${student.weeklyHours} hrs/week`
                                    : null,
                                student.hourlyRate
                                    ? `₱ ${student.hourlyRate}/hr`
                                    : null,
                                student.responseTimeHours
                                    ? `responds within ${student.responseTimeHours} hrs`
                                    : null,
                                student.location,
                            ]
                                .filter(Boolean)
                                .join(' · ') || 'Not stated yet'}
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

/**
 * The student's photo, or a stand-in.
 *
 * `alt=""` on purpose: the name is in the heading right beside this, so the
 * picture is decorative and a screen reader announcing it twice is noise.
 * It also matters when the file is missing — a broken image with alt text
 * paints that text at the parent's 3xl size, which is what QA saw spilling
 * out of the circle and running under the name.
 *
 * onError covers the same case deliberately rather than by accident: uploads
 * live on the container filesystem, so on a host with no persistent volume
 * every avatar 404s after a redeploy while the column still points at it.
 */
function StudentAvatar({ url }: { url: string | null }) {
    const [failed, setFailed] = useState(false);
    const showImage = url !== null && !failed;

    return (
        <span className="grid size-16 shrink-0 place-items-center overflow-hidden rounded-full bg-primary/15 text-3xl text-primary">
            {showImage ? (
                <img
                    src={url}
                    alt=""
                    className="size-full object-cover"
                    onError={() => setFailed(true)}
                />
            ) : (
                <UserIcon />
            )}
        </span>
    );
}
