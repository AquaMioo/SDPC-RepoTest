import { Head, Link, router } from '@inertiajs/react';
import {
    MagnifyingGlassIcon,
    SparkleIcon,
    StarIcon,
    UserIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';
import { Panel, PanelAccent, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import ToggleField from '@/components/sdpc/toggle-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { store as messagesStore } from '@/routes/messages';
import { index as recruitIndex } from '@/routes/recruit';
import { show as studentsShow } from '@/routes/students';
import type { Paginated, SelectOption, StudentCard } from '@/types/client';

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
    options: {
        sorts: SelectOption[];
        schools: { slug: string; name: string }[];
        courses: { slug: string; name: string; abbreviation: string | null }[];
        skillGroups: {
            type: string;
            label: string;
            skills: { slug: string; name: string }[];
        }[];
    };
    context: { slug: string; title: string } | null;
    matchingEnabled: boolean;
    /** What building the searched-for system actually takes. */
    scopeSkills: { slug: string; name: string; isRequired: boolean }[];
};

export default function Recruit({
    students,
    filters,
    options,
    context,
    matchingEnabled,
    scopeSkills,
}: Props) {
    const team = useCurrentTeam();
    const [search, setSearch] = useState(filters.search ?? '');

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

    const toggleSkill = (slug: string) => {
        const next = filters.skills.includes(slug)
            ? filters.skills.filter((s) => s !== slug)
            : [...filters.skills, slug];

        apply({ skills: next });
    };

    return (
        <>
            <Head title="Recruit" />

            <div className="mx-auto grid max-w-[1320px] items-start gap-6 px-8 pt-[30px] pb-[72px] lg:grid-cols-[minmax(0,1fr)_300px]">
                <div className="min-w-0">
                    <div className="mb-5 flex flex-wrap items-end gap-4">
                        <div className="mr-auto">
                            <h3 className="m-0">
                                {context
                                    ? 'Recommended students'
                                    : 'Student developers'}
                            </h3>
                            <div className="text-[13px] text-muted-foreground">
                                {context
                                    ? `Ranked for "${context.title}"`
                                    : `${students.total} student${students.total === 1 ? '' : 's'} on the platform`}
                            </div>
                        </div>

                        <form
                            className="relative w-[250px]"
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
                            />
                        </form>

                        <select
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            value={filters.sort}
                            aria-label="Sort students"
                            onChange={(e) => apply({ sort: e.target.value })}
                        >
                            {options.sorts.map((sort) => (
                                <option key={sort.value} value={sort.value}>
                                    {sort.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    {students.data.length === 0 ? (
                        <Panel padding="lg" gap="lg" className="items-start">
                            <h6 className="m-0">
                                No students match those filters
                            </h6>
                            <p className="m-0 max-w-[52ch] text-[13px] leading-relaxed text-muted-foreground">
                                Try removing a skill filter, or widen the school
                                and availability options.
                            </p>
                        </Panel>
                    ) : (
                        <div className="grid [grid-template-columns:repeat(auto-fill,minmax(215px,1fr))] gap-4">
                            {students.data.map((student) => (
                                /*
                                 * h-full + mt-auto on the button is what keeps
                                 * a row of these aligned. Compatibility, the
                                 * insight blurb and the skills row are all
                                 * conditional, so without it every card in the
                                 * row ends at a different height.
                                 */
                                <Panel
                                    key={student.id}
                                    padding="lg"
                                    className="h-full items-center text-center"
                                >
                                    <span className="grid size-16 place-items-center rounded-full bg-primary/15 text-3xl text-primary">
                                        <UserIcon />
                                    </span>

                                    <div>
                                        <div className="text-[15px] font-semibold">
                                            {student.name}
                                        </div>
                                        <div className="line-clamp-2 text-[12px] text-primary">
                                            {student.headline ?? student.course}
                                        </div>
                                    </div>

                                    {student.skills.length > 0 && (
                                        <div className="flex flex-wrap justify-center gap-1.5">
                                            {student.skills
                                                .slice(0, 2)
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

                                    <div className="flex items-center gap-1.5 text-[12px] text-muted-foreground">
                                        <StarIcon
                                            weight="fill"
                                            className="text-primary"
                                        />
                                        {student.rating.toFixed(1)} ·{' '}
                                        {student.completedProjects} projects
                                    </div>

                                    {student.compatibility !== null && (
                                        <Tag variant="accent">
                                            {student.compatibility}% match
                                        </Tag>
                                    )}

                                    {/* The reasoning, not just the number —
                                        a percentage alone decides nothing. */}
                                    {student.insight && (
                                        <div className="rounded-md bg-primary/10 px-2.5 py-2 text-[11px] leading-relaxed">
                                            <span className="text-primary">
                                                Why
                                            </span>{' '}
                                            · {student.insight}
                                        </div>
                                    )}

                                    <div className="mt-auto grid w-full grid-cols-2 gap-2">
                                        <Button
                                            variant="secondary"
                                            onClick={() => message(student)}
                                        >
                                            Message
                                        </Button>
                                        <Button asChild variant="outline">
                                            <Link
                                                href={studentsShow.url({
                                                    current_team: team.slug,
                                                    user: student.id,
                                                })}
                                            >
                                                Profile
                                            </Link>
                                        </Button>
                                    </div>
                                </Panel>
                            ))}
                        </div>
                    )}
                </div>

                <aside className="sticky top-[88px] flex flex-col gap-4">
                    {matchingEnabled ? (
                        <PanelAccent>
                            <div className="flex items-center gap-2">
                                <SparkleIcon className="text-primary" />
                                <PanelKicker className="mr-auto">
                                    Matching
                                </PanelKicker>
                            </div>
                            <p className="m-0 text-[13px] leading-relaxed opacity-85">
                                {context
                                    ? `Students are ranked against "${context.title}".`
                                    : 'Students are ranked against what you described, best fit first.'}
                            </p>

                            {scopeSkills.length > 0 && (
                                <>
                                    <PanelKicker>
                                        Building this takes
                                    </PanelKicker>
                                    <div className="flex flex-wrap gap-1.5">
                                        {scopeSkills.map((skill) => (
                                            <Tag
                                                key={skill.slug}
                                                variant={
                                                    skill.isRequired
                                                        ? 'accent'
                                                        : 'outline'
                                                }
                                            >
                                                {skill.name}
                                            </Tag>
                                        ))}
                                    </div>
                                    <p className="m-0 text-[11.5px] leading-relaxed text-muted-foreground">
                                        Read out of what you asked for. Filled
                                        chips were named on the posting;
                                        outlined ones are implied by the kind of
                                        system.
                                    </p>
                                </>
                            )}
                        </PanelAccent>
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

                    <Panel padding="lg" gap="lg">
                        <PanelKicker>Filters</PanelKicker>

                        <ToggleField
                            label="Available only"
                            checked={filters.availableOnly}
                            onChange={(e) =>
                                apply({
                                    available_only:
                                        e.target.checked || undefined,
                                })
                            }
                        />

                        <select
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            value={filters.school ?? ''}
                            aria-label="Filter by school"
                            onChange={(e) =>
                                apply({ school: e.target.value || undefined })
                            }
                        >
                            <option value="">All schools</option>
                            {options.schools.map((school) => (
                                <option key={school.slug} value={school.slug}>
                                    {school.name}
                                </option>
                            ))}
                        </select>

                        <select
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            value={filters.course ?? ''}
                            aria-label="Filter by course"
                            onChange={(e) =>
                                apply({ course: e.target.value || undefined })
                            }
                        >
                            <option value="">All courses</option>
                            {options.courses.map((course) => (
                                <option key={course.slug} value={course.slug}>
                                    {course.abbreviation ?? course.name}
                                </option>
                            ))}
                        </select>
                    </Panel>

                    {options.skillGroups.map((group) => (
                        <Panel key={group.type} padding="lg" gap="sm">
                            <PanelKicker>{group.label}</PanelKicker>
                            <div className="flex flex-wrap gap-1.5">
                                {group.skills.map((skill) => (
                                    <button
                                        key={skill.slug}
                                        type="button"
                                        onClick={() => toggleSkill(skill.slug)}
                                        aria-pressed={filters.skills.includes(
                                            skill.slug,
                                        )}
                                        className="cursor-pointer"
                                    >
                                        <Tag
                                            variant={
                                                filters.skills.includes(
                                                    skill.slug,
                                                )
                                                    ? 'accent'
                                                    : 'outline'
                                            }
                                        >
                                            {skill.name}
                                        </Tag>
                                    </button>
                                ))}
                            </div>
                        </Panel>
                    ))}
                </aside>
            </div>
        </>
    );
}
