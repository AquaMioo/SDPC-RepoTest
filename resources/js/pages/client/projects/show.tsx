import { Deferred, Head, Link, router } from '@inertiajs/react';
import { PencilSimpleIcon, UsersThreeIcon } from '@phosphor-icons/react';
import { Panel, PanelDivider, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { Button } from '@/components/ui/button';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { archive, edit as projectsEdit } from '@/routes/projects';
import { index as applicantsIndex } from '@/routes/projects/applicants';
import { toggle as intakeToggle } from '@/routes/projects/intake';
import { show as studentsShow } from '@/routes/students';

type Props = {
    project: {
        slug: string;
        title: string;
        description: string;
        objectives: string | null;
        category: string;
        industry: string | null;
        statusLabel: string;
        isEditable: boolean;
        isDraft: boolean;
        applicationsOpen: boolean;
        isAcceptingApplications: boolean;
        skills: string[];
        attachments: { id: number; name: string; size: string }[];
    };
    applicantCounts: { total: number; pending: number; accepted: number };
    /**
     * Deferred: scoring calls a model, so the brief renders first and the
     * candidates arrive after. Undefined while in flight, [] when the posting
     * is not live or nothing scored.
     */
    recommended?: {
        id: number;
        name: string;
        headline: string | null;
        course: string | null;
        yearLevel: number | null;
        compatibility: number;
        insight: string | null;
    }[];
};

export default function ShowProject({
    project,
    applicantCounts,
    recommended,
}: Props) {
    const team = useCurrentTeam();
    const routeArgs = { current_team: team.slug, project: project.slug };

    const facts = [
        { label: 'Category', value: project.category },
        { label: 'Industry', value: project.industry },
    ].filter((fact) => fact.value);

    return (
        <>
            <Head title={project.title} />

            <div className="mx-auto grid max-w-[clamp(1160px,100vw_-_320px,1600px)] items-start gap-6 px-4 pt-[30px] pb-[72px] sm:px-6 lg:grid-cols-[minmax(0,1fr)_300px] lg:px-8">
                <div className="flex min-w-0 flex-col gap-4">
                    <div className="flex flex-wrap items-end gap-4">
                        <div className="mr-auto">
                            <h3 className="m-0">{project.title}</h3>
                            <div className="text-[13px] text-muted-foreground">
                                {project.category}
                            </div>
                        </div>
                        <Tag
                            variant={
                                project.isAcceptingApplications
                                    ? 'accent'
                                    : 'outline'
                            }
                        >
                            {project.statusLabel}
                        </Tag>
                    </div>

                    <Panel padding="lg" gap="lg">
                        <h6 className="m-0">Overview</h6>
                        <p className="m-0 text-[13.5px] leading-relaxed whitespace-pre-line opacity-85">
                            {project.description}
                        </p>

                        {project.objectives && (
                            <>
                                <PanelDivider />
                                <PanelKicker>Objectives</PanelKicker>
                                <ul className="m-0 flex list-disc flex-col gap-1 pl-4 text-[13px] opacity-85">
                                    {project.objectives
                                        .split('\n')
                                        .filter(Boolean)
                                        .map((objective) => (
                                            <li key={objective}>{objective}</li>
                                        ))}
                                </ul>
                            </>
                        )}

                        {project.skills.length > 0 && (
                            <>
                                <PanelDivider />
                                <PanelKicker>Required skills</PanelKicker>
                                <div className="flex flex-wrap gap-1.5">
                                    {project.skills.map((skill) => (
                                        <Tag key={skill} variant="accent">
                                            {skill}
                                        </Tag>
                                    ))}
                                </div>
                            </>
                        )}
                    </Panel>

                    <Panel padding="lg" gap="lg">
                        <h6 className="m-0">Details</h6>
                        <dl className="grid gap-3 sm:grid-cols-2">
                            {facts.map((fact) => (
                                <div key={fact.label}>
                                    <dt className="text-[11px] tracking-[0.08em] text-muted-foreground uppercase">
                                        {fact.label}
                                    </dt>
                                    <dd className="m-0 text-[13.5px]">
                                        {fact.value}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </Panel>
                </div>

                <aside className="sticky top-[88px] flex flex-col gap-4">
                    <Panel padding="lg" gap="lg">
                        <PanelKicker>Applicants</PanelKicker>
                        <div className="flex items-baseline gap-2.5">
                            <span className="font-heading text-[32px] leading-none">
                                {applicantCounts.total}
                            </span>
                            <span className="text-[12px] text-muted-foreground">
                                {applicantCounts.pending} awaiting a decision
                            </span>
                        </div>
                        <Button asChild className="w-full">
                            <Link href={applicantsIndex.url(routeArgs)}>
                                <UsersThreeIcon />
                                Review applicants
                            </Link>
                        </Button>
                    </Panel>

                    {/* Only for a live posting: a draft has not been
                        approved and no student can see it, so there is
                        nobody to recommend for it yet. */}
                    {project.applicationsOpen && !project.isDraft && (
                        <Panel padding="lg" gap="lg">
                            <PanelKicker>AI recommended students</PanelKicker>

                            <Deferred
                                data="recommended"
                                fallback={
                                    <div className="flex flex-col gap-2">
                                        {[0, 1, 2].map((row) => (
                                            <div
                                                key={row}
                                                className="h-12 animate-pulse rounded bg-muted"
                                            />
                                        ))}
                                    </div>
                                }
                            >
                                {recommended === undefined ||
                                recommended.length === 0 ? (
                                    <p className="m-0 text-[13px] text-muted-foreground">
                                        No candidates to suggest yet. Students
                                        appear here once profiles have enough to
                                        match your brief against.
                                    </p>
                                ) : (
                                    <div className="flex flex-col gap-3">
                                        {recommended.map((student) => (
                                            <div
                                                key={student.id}
                                                className="flex items-start gap-3"
                                            >
                                                {/* break-words as well as min-w-0: the flex rule only lets
                                                    the column shrink, and an unbroken run —
                                                    a long headline word, a course code, a
                                                    pasted URL — still spilled past the card
                                                    border. overflow-wrap is inherited, so the
                                                    name, the headline and the insight are all
                                                    covered from here. */}
                                                <div className="min-w-0 flex-1 break-words">
                                                    <Link
                                                        href={studentsShow.url({
                                                            current_team:
                                                                team.slug,
                                                            user: student.id,
                                                        })}
                                                        className="text-[14px] font-semibold hover:underline"
                                                    >
                                                        {student.name}
                                                    </Link>
                                                    <div className="text-[12px] text-muted-foreground">
                                                        {[
                                                            student.headline,
                                                            student.course,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </div>
                                                    {student.insight && (
                                                        <p className="m-0 mt-1 text-[12px] leading-relaxed opacity-85">
                                                            {student.insight}
                                                        </p>
                                                    )}
                                                </div>
                                                <Tag variant="accent">
                                                    {student.compatibility}%
                                                </Tag>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </Deferred>
                        </Panel>
                    )}

                    <Panel padding="lg" gap="lg">
                        <PanelKicker>Manage</PanelKicker>

                        {/* A draft looks identical to a live posting from
                            here, which is how somebody saves one and then
                            wonders why no student ever sees it. */}
                        {project.isDraft && (
                            <p className="m-0 text-[12.5px] leading-relaxed text-muted-foreground">
                                This is a draft. Students cannot see it until
                                you publish it — open{' '}
                                <strong className="font-medium">
                                    Edit posting
                                </strong>{' '}
                                and press Publish.
                            </p>
                        )}

                        {project.isEditable && (
                            <>
                                <Button
                                    asChild
                                    variant="secondary"
                                    className="w-full"
                                >
                                    <Link href={projectsEdit.url(routeArgs)}>
                                        <PencilSimpleIcon />
                                        Edit posting
                                    </Link>
                                </Button>

                                <Button
                                    variant="secondary"
                                    className="w-full"
                                    onClick={() =>
                                        router.patch(
                                            intakeToggle.url(routeArgs),
                                        )
                                    }
                                >
                                    {project.applicationsOpen
                                        ? 'Close applications'
                                        : 'Reopen applications'}
                                </Button>
                            </>
                        )}

                        <Button
                            variant="secondary"
                            className="w-full"
                            onClick={() => router.patch(archive.url(routeArgs))}
                        >
                            Archive project
                        </Button>
                    </Panel>
                </aside>
            </div>
        </>
    );
}
