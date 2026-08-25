import { Head, Link } from '@inertiajs/react';
import { InfoIcon, PlusIcon, UsersThreeIcon } from '@phosphor-icons/react';
import { Panel } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { Button } from '@/components/ui/button';
import { useCurrentTeam } from '@/hooks/use-current-team';
import {
    create as projectsCreate,
    show as projectsShow,
} from '@/routes/projects';
import { index as applicantsIndex } from '@/routes/projects/applicants';
import type { Paginated, ProjectListItem } from '@/types/client';

type Props = {
    projects: Paginated<ProjectListItem>;
    canCreate: boolean;
};

export default function ProjectsIndex({ projects, canCreate }: Props) {
    const team = useCurrentTeam();

    return (
        <>
            <Head title="Projects" />

            <div className="mx-auto max-w-[1320px] px-4 pt-[30px] pb-[72px] sm:px-6 lg:px-8">
                <div className="mb-5 flex items-end gap-4">
                    <div className="mr-auto">
                        <h3 className="m-0">Your projects</h3>
                        <div className="text-[13px] text-muted-foreground">
                            {projects.total} posting
                            {projects.total === 1 ? '' : 's'} from {team.name}
                        </div>
                    </div>
                    {canCreate && (
                        <Button asChild>
                            <Link href={projectsCreate.url(team.slug)}>
                                <PlusIcon />
                                Post a project
                            </Link>
                        </Button>
                    )}
                </div>

                {/*
                 * One posting at a time, so the board says why the post button
                 * is gone rather than leaving a client hunting for it.
                 * `.card` is unlayered CSS and wins over Tailwind's
                 * flex-direction, hence the inner row instead of `flex-row`.
                 */}
                {!canCreate && projects.total > 0 && (
                    <Panel className="mb-4">
                        <div className="flex items-center gap-2.5">
                            <InfoIcon className="shrink-0 text-muted-foreground" />
                            <p className="m-0 text-[12.5px] text-muted-foreground">
                                You can run one project at a time. Complete or
                                archive your current posting to start a new one.
                            </p>
                        </div>
                    </Panel>
                )}

                {projects.data.length === 0 ? (
                    <Panel padding="lg" gap="lg" className="items-start">
                        <h6 className="m-0">No projects yet</h6>
                        <p className="m-0 max-w-[52ch] text-[13px] leading-relaxed text-muted-foreground">
                            Post your first project to start matching with
                            student developers. Postings are screened by an
                            administrator before they go live.
                        </p>
                        {canCreate && (
                            <Button asChild>
                                <Link href={projectsCreate.url(team.slug)}>
                                    <PlusIcon />
                                    Post a project
                                </Link>
                            </Button>
                        )}
                    </Panel>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {projects.data.map((project) => (
                            <Panel
                                key={project.slug}
                                padding="lg"
                                gap="lg"
                                className="h-full"
                            >
                                <div className="flex items-start gap-2">
                                    <Link
                                        href={projectsShow.url({
                                            current_team: team.slug,
                                            project: project.slug,
                                        })}
                                        className="mr-auto text-[15px] leading-snug font-semibold hover:underline"
                                    >
                                        {project.title}
                                    </Link>
                                    <Tag
                                        variant={
                                            project.applicationsOpen
                                                ? 'accent'
                                                : 'neutral'
                                        }
                                    >
                                        {project.statusLabel}
                                    </Tag>
                                </div>

                                <div className="text-[12px] text-muted-foreground">
                                    {project.category}
                                </div>

                                {project.skills.length > 0 && (
                                    <div className="flex flex-wrap gap-1.5">
                                        {project.skills
                                            .slice(0, 4)
                                            .map((skill) => (
                                                <Tag
                                                    key={skill}
                                                    variant="neutral"
                                                >
                                                    {skill}
                                                </Tag>
                                            ))}
                                    </div>
                                )}

                                {/* Pinned to the bottom so the applicant row
                                    lines up across cards whose skill lists
                                    are different lengths. */}
                                <div className="mt-auto flex items-center gap-3 text-[12px] text-muted-foreground">
                                    <span className="mr-auto">
                                        {project.applicationsCount} applicant
                                        {project.applicationsCount === 1
                                            ? ''
                                            : 's'}
                                    </span>
                                    <Link
                                        href={applicantsIndex.url({
                                            current_team: team.slug,
                                            project: project.slug,
                                        })}
                                        className="flex items-center gap-1.5 hover:text-primary"
                                    >
                                        <UsersThreeIcon />
                                        {project.applicationsCount}
                                        {project.pendingApplicationsCount >
                                            0 && (
                                            <span className="text-primary">
                                                (
                                                {
                                                    project.pendingApplicationsCount
                                                }{' '}
                                                new)
                                            </span>
                                        )}
                                    </Link>
                                </div>
                            </Panel>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
