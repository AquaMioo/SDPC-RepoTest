import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircleIcon,
    MagnifyingGlassIcon,
    SealCheckIcon,
    SparkleIcon,
    StarIcon,
    UserIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';
import BriefDialog from '@/components/sdpc/brief-dialog';
import { Btn } from '@/components/sdpc/btn';
import { Panel, PanelAccent, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { Input } from '@/components/ui/input';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { store as messagesStore } from '@/routes/messages';
import { index as recruitIndex } from '@/routes/recruit';
import { show as studentsShow } from '@/routes/students';
import type { Paginated, StudentCard } from '@/types/client';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

type Props = {
    students: Paginated<StudentCard>;
    filters: {
        search: string | null;
        skills: string[];
        school: string | null;
        course: string | null;
        availableOnly: boolean;
        sort: string;
    };
    /** What the screen is ranking against, when a brief was described. */
    idea: { title: string; description: string };
    context: { slug: string; title: string } | null;
    matchingEnabled: boolean;
    /** What building the searched-for system actually takes. */
    scopeSkills: { slug: string; name: string; isRequired: boolean }[];
    /** The strongest match on this page, or null when nothing was scored. */
    highlight: {
        name: string;
        compatibility: number;
        factors: { label: string; value: number }[];
        recommendation: string | null;
        matchedSkills: string[];
    } | null;
};

export default function Recruit({
    students,
    filters,
    idea,
    context,
    matchingEnabled,
    scopeSkills,
    highlight,
}: Props) {
    const team = useCurrentTeam();
    const [search, setSearch] = useState(filters.search ?? '');
    const [ideaOpen, setIdeaOpen] = useState(false);

    const rankingByIdea = idea.title !== '' || idea.description !== '';

    const apply = (patch: Record<string, unknown>) => {
        router.get(
            recruitIndex.url(team.slug),
            {
                search: search || undefined,
                skills: filters.skills,
                school: filters.school ?? undefined,
                course: filters.course ?? undefined,
                available_only: filters.availableOnly || undefined,
                sort: filters.sort,
                project: context?.slug,
                /* Carried on every visit, or picking a sort would silently
                   drop the brief the page is ranked against. */
                idea_title: idea.title || undefined,
                idea_description: idea.description || undefined,
                ...patch,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    /*
     * A thread needs an application row behind it, so Message only opens one
     * for students already invited or hired. Everyone else goes to the
     * profile, which is where the invitation that unlocks messaging lives —
     * the button stays useful either way instead of 403ing.
     */
    const message = (student: StudentCard) => {
        const profile = studentsShow.url({
            current_team: team.slug,
            user: student.id,
        });

        if (student.messageableProjectId === null) {
            router.get(profile);

            return;
        }

        router.post(messagesStore.url(team.slug), {
            project_id: student.messageableProjectId,
            user_id: student.id,
        });
    };

    return (
        <>
            <Head title="Recruit" />

            <div className="mx-auto grid max-w-[clamp(1320px,100vw_-_320px,1600px)] items-start gap-6 px-4 pt-[30px] pb-[72px] sm:px-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:px-8">
                <div className="min-w-0">
                    <div className="mb-5 flex flex-wrap items-end gap-3">
                        <div className="mr-auto">
                            <h3 className="m-0">
                                {context
                                    ? 'Recommended students'
                                    : 'Student developers'}
                            </h3>
                            <div className="text-[13px] text-muted-foreground">
                                {context
                                    ? `Ranked for "${context.title}"`
                                    : rankingByIdea && idea.title !== ''
                                      ? `Ranked for "${idea.title}"`
                                      : `${students.total} student${students.total === 1 ? '' : 's'} on the platform`}
                            </div>
                        </div>

                        <form
                            className="relative w-[260px]"
                            onSubmit={(event) => {
                                event.preventDefault();
                                apply({});
                            }}
                        >
                            <MagnifyingGlassIcon className="absolute top-2.5 left-2.5 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search skills or school"
                                aria-label="Search students"
                                className="pl-8"
                                /*
                                 * Clicking the box asks what you want built
                                 * rather than what you want to type. onClick
                                 * and not onFocus: Radix returns focus here
                                 * when the dialog closes, and on focus it
                                 * would reopen itself immediately.
                                 */
                                onClick={() => setIdeaOpen(true)}
                            />
                        </form>
                    </div>

                    {students.data.length === 0 ? (
                        <Panel padding="lg" gap="lg" className="items-start">
                            <h6 className="m-0">No students to show</h6>
                            <p className="m-0 max-w-[52ch] text-[13px] leading-relaxed text-muted-foreground">
                                Try describing the system you want built, or
                                search by a name or school.
                            </p>
                        </Panel>
                    ) : (
                        <Panel padding="lg" gap="none">
                            {students.data.map((student, index) => (
                                <StudentRow
                                    key={student.id}
                                    student={student}
                                    isFirst={index === 0}
                                    profileHref={studentsShow.url({
                                        current_team: team.slug,
                                        user: student.id,
                                    })}
                                    onMessage={() => message(student)}
                                />
                            ))}
                        </Panel>
                    )}

                    {/*
                     * Page numbers, not a reload. `only` keeps the visit to
                     * the list — without it every prop is rebuilt and the
                     * whole set is re-scored through the model just to show
                     * five different people.
                     */}
                    {students.links.length > 3 && (
                        <div className="mt-5 flex flex-wrap gap-1.5">
                            {students.links.map((link, index) =>
                                link.url === null ? (
                                    <span
                                        key={index}
                                        className="btn btn-ghost opacity-40"
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ) : (
                                    <Link
                                        key={index}
                                        href={link.url}
                                        only={['students']}
                                        preserveScroll
                                        preserveState
                                        className={
                                            link.active
                                                ? 'btn btn-primary'
                                                : 'btn btn-ghost'
                                        }
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ),
                            )}
                        </div>
                    )}

                    <BriefDialog
                        open={ideaOpen}
                        onOpenChange={setIdeaOpen}
                        heading="Your business idea"
                        description="Tell us about your business idea."
                        titleLabel="Project title"
                        titlePlaceholder="Ex: Inventory System with Predictive Analytics"
                        bodyLabel="Project description"
                        bodyPlaceholder="What should the system do, who will use it, and what stage are you at?"
                        value={idea}
                        onConfirm={(next) =>
                            apply({
                                idea_title: next.title || undefined,
                                idea_description: next.description || undefined,
                            })
                        }
                    />
                </div>

                <aside className="sticky top-[88px] flex max-h-[calc(100vh-108px)] flex-col gap-4 overflow-y-auto">
                    {matchingEnabled && highlight !== null ? (
                        <MatchingPanel
                            highlight={highlight}
                            context={context}
                            scopeSkills={scopeSkills}
                        />
                    ) : (
                        <Panel padding="lg" gap="lg">
                            <PanelKicker>Matching</PanelKicker>
                            <p className="m-0 text-[12.5px] leading-relaxed text-muted-foreground">
                                Describe the system you want built, in your own
                                words, and students are ranked by who can
                                actually build it. Filtering against one of your
                                postings does the same.
                            </p>
                        </Panel>
                    )}
                </aside>
            </div>
        </>
    );
}

/**
 * One student, as a full-width row.
 *
 * The design also sketched a ratings-and-reviews wall with quoted feedback.
 * There is no review table on this platform — a student's rating_average and
 * completed_projects_count are the only figures behind it — so the quotes are
 * absent rather than invented, and a student nobody has finished a project
 * with says so instead of showing a 0.0.
 */
function StudentRow({
    student,
    profileHref,
    isFirst,
    onMessage,
}: {
    student: StudentCard;
    profileHref: string;
    isFirst: boolean;
    onMessage: () => void;
}) {
    /*
     * "4th yr BSIT" is one fact, not two, so the year and the course are
     * joined by a space and only then separated from the headline.
     */
    const standing = [
        student.yearLevel === null ? null : `${ordinal(student.yearLevel)} yr`,
        student.course,
    ]
        .filter(Boolean)
        .join(' ');

    const credentials = [standing, student.headline]
        .filter(Boolean)
        .join(' · ');

    return (
        <article
            data-test="student-row"
            className={
                isFirst
                    ? 'flex flex-col gap-2.5 pb-5'
                    : 'flex flex-col gap-2.5 border-t border-[var(--color-divider)] py-5'
            }
        >
            <div className="flex flex-wrap items-start gap-3">
                <span className="grid size-11 flex-none place-items-center rounded-full bg-primary/15 text-xl text-primary">
                    <UserIcon />
                </span>

                <div className="mr-auto min-w-0">
                    <div className="flex items-center gap-1.5">
                        <Link
                            href={profileHref}
                            className="text-[15px] font-semibold text-[var(--color-text)] no-underline"
                        >
                            {student.name}
                        </Link>
                        {student.isVerified && (
                            <SealCheckIcon
                                weight="fill"
                                className="text-primary"
                                aria-label="Verified student"
                            />
                        )}
                    </div>

                    {credentials && (
                        <div className="text-[13px] text-primary">
                            {credentials}
                        </div>
                    )}

                    <div className="text-[11.5px] text-muted-foreground">
                        {[student.location, student.school]
                            .filter(Boolean)
                            .join(' · ')}
                    </div>
                </div>

                {/*
                 * Inviting happens on the profile: an invitation needs one of
                 * this team's postings picked, and that list lives there. The
                 * button is the way in rather than a second, half copy of it.
                 */}
                <Btn asChild variant="primary">
                    <Link href={profileHref}>Invite to job</Link>
                </Btn>
            </div>

            {student.skills.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {student.skills.map((skill) => (
                        <Tag key={skill} variant="outline">
                            {skill}
                        </Tag>
                    ))}
                </div>
            )}

            {student.highlights.length > 0 && (
                <div className="flex flex-wrap gap-x-6 gap-y-1.5">
                    {student.highlights.map((highlight) => (
                        <span
                            key={highlight}
                            className="inline-flex items-center gap-1.5 text-[12px] text-muted-foreground"
                        >
                            <CheckCircleIcon
                                weight="fill"
                                className="flex-none text-primary"
                            />
                            {highlight}
                        </span>
                    ))}
                </div>
            )}

            {/* The reasoning, not just the number — a percentage alone
                decides nothing. */}
            {student.insight && (
                <div className="max-w-[76ch] rounded-md bg-primary/10 px-2.5 py-2 text-[11.5px] leading-relaxed">
                    <span className="text-primary">Why</span> ·{' '}
                    {student.insight}
                </div>
            )}

            <div className="mt-1 flex flex-wrap items-center gap-2.5 border-t border-[var(--color-divider)] pt-3">
                <span className="mr-auto inline-flex items-center gap-1.5 text-[12px] text-muted-foreground">
                    {student.completedProjects === 0 ? (
                        'No finished projects yet'
                    ) : (
                        <>
                            <StarIcon weight="fill" className="text-primary" />
                            {student.rating.toFixed(1)} rating ·{' '}
                            {student.completedProjects} project
                            {student.completedProjects === 1 ? '' : 's'}
                        </>
                    )}
                </span>

                {student.compatibility !== null && (
                    <Tag variant="accent">{student.compatibility}% match</Tag>
                )}

                <Btn
                    variant="ghost"
                    title={
                        student.messageableProjectId === null
                            ? 'Invite this student to one of your postings first — an invitation is what opens a thread.'
                            : undefined
                    }
                    onClick={onMessage}
                >
                    Message
                </Btn>

                <Btn asChild variant="secondary">
                    <Link href={profileHref}>View profile</Link>
                </Btn>
            </div>
        </article>
    );
}

/**
 * The matching rail: how the strongest fit on this page scored, and why.
 *
 * The per-factor bars are the half of the answer a single percentage cannot
 * give. Every figure comes from the matching engine — nothing here is a
 * placeholder, so a page with nothing scored gets no rail at all.
 */
function MatchingPanel({
    highlight,
    context,
    scopeSkills,
}: {
    highlight: NonNullable<Props['highlight']>;
    context: Props['context'];
    scopeSkills: Props['scopeSkills'];
}) {
    return (
        <PanelAccent>
            <div className="flex items-center gap-2">
                <SparkleIcon className="text-primary" />
                <PanelKicker className="mr-auto">Matching</PanelKicker>
                <span className="text-[11px] text-muted-foreground">
                    {highlight.compatibility}% top
                </span>
            </div>

            <p className="m-0 text-[12.5px] leading-relaxed opacity-85">
                {context
                    ? `Students are ranked against "${context.title}". ${highlight.name} fits best.`
                    : `Students are ranked against what you described. ${highlight.name} fits best.`}
            </p>

            {highlight.factors.length > 0 && (
                <div className="flex flex-col gap-2.5">
                    {highlight.factors.map((factor) => (
                        <div key={factor.label}>
                            <div className="mb-1 flex justify-between text-[11.5px]">
                                <span>{factor.label}</span>
                                <span style={{ color: MUTED(68) }}>
                                    {factor.value}%
                                </span>
                            </div>
                            <div
                                role="progressbar"
                                aria-label={factor.label}
                                aria-valuenow={factor.value}
                                aria-valuemin={0}
                                aria-valuemax={100}
                                className="h-[5px] rounded-full bg-[var(--color-divider)]"
                            >
                                <div
                                    className="h-full rounded-full bg-primary"
                                    style={{ width: `${factor.value}%` }}
                                />
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {highlight.matchedSkills.length > 0 && (
                <>
                    <PanelKicker>Top matching skills</PanelKicker>
                    <div className="flex flex-wrap gap-1.5">
                        {highlight.matchedSkills.map((skill) => (
                            <Tag key={skill} variant="accent">
                                {skill}
                            </Tag>
                        ))}
                    </div>
                </>
            )}

            {highlight.recommendation && (
                <>
                    <PanelKicker>Strategic recommendation</PanelKicker>
                    <p className="m-0 text-[12px] leading-relaxed text-muted-foreground">
                        {highlight.recommendation}
                    </p>
                </>
            )}

            {scopeSkills.length > 0 && (
                <>
                    <PanelKicker>Building this takes</PanelKicker>
                    <div className="flex flex-wrap gap-1.5">
                        {scopeSkills.map((skill) => (
                            <Tag
                                key={skill.slug}
                                variant={
                                    skill.isRequired ? 'accent' : 'outline'
                                }
                            >
                                {skill.name}
                            </Tag>
                        ))}
                    </div>
                    <p className="m-0 text-[11.5px] leading-relaxed text-muted-foreground">
                        Read out of what you asked for. Filled chips were named
                        on the posting; outlined ones are implied by the kind of
                        system.
                    </p>
                </>
            )}
        </PanelAccent>
    );
}

/**
 * "4th" rather than "4" — the row reads as a sentence, not a data dump.
 */
function ordinal(value: number): string {
    const remainderTen = value % 10;
    const remainderHundred = value % 100;

    if (remainderTen === 1 && remainderHundred !== 11) {
        return `${value}st`;
    }

    if (remainderTen === 2 && remainderHundred !== 12) {
        return `${value}nd`;
    }

    if (remainderTen === 3 && remainderHundred !== 13) {
        return `${value}rd`;
    }

    return `${value}th`;
}
