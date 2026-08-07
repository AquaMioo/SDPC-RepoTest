import { Head, Link, router } from '@inertiajs/react';
import {
    MagnifyingGlassIcon,
    SparkleIcon,
    StarIcon,
    UserIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';
import Meter from '@/components/sdpc/meter';
import { Panel, PanelAccent, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import ToggleField from '@/components/sdpc/toggle-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCurrentTeam } from '@/hooks/use-current-team';
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
};

export default function Recruit({
    students,
    filters,
    options,
    context,
    matchingEnabled,
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
                                placeholder="Search skills, school, rate"
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
                                <Panel
                                    key={student.id}
                                    padding="lg"
                                    className="items-center text-center"
                                >
                                    <span className="grid size-16 place-items-center rounded-full bg-primary/15 text-3xl text-primary">
                                        <UserIcon />
                                    </span>

                                    <div>
                                        <div className="text-[15px] font-semibold">
                                            {student.name}
                                        </div>
                                        <div className="text-[12px] text-muted-foreground">
                                            {student.headline ?? student.course}
                                        </div>
                                    </div>

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

                                    <div className="flex items-center gap-1.5 text-[12px] text-muted-foreground">
                                        <StarIcon
                                            weight="fill"
                                            className="text-primary"
                                        />
                                        {student.rating} ·{' '}
                                        {student.completedProjects} projects
                                    </div>

                                    {student.compatibility !== null && (
                                        <Tag variant="accent">
                                            {student.compatibility}% match
                                        </Tag>
                                    )}

                                    <Button asChild className="mt-0.5 w-full">
                                        <Link
                                            href={studentsShow.url({
                                                current_team: team.slug,
                                                user: student.id,
                                            })}
                                        >
                                            Profile
                                        </Link>
                                    </Button>
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
                                    AI matching
                                </PanelKicker>
                            </div>
                            <p className="m-0 text-[13px] leading-relaxed opacity-85">
                                Ranking is weighted on skills, availability this
                                term, and documented past work.
                            </p>
                            <Meter label="Skill match" value={92} />
                        </PanelAccent>
                    ) : (
                        <Panel padding="lg" gap="lg">
                            <PanelKicker>Matching</PanelKicker>
                            <p className="m-0 text-[12.5px] leading-relaxed text-muted-foreground">
                                Automatic ranking arrives with the AI module.
                                Until then students are ordered by rating and
                                completed work.
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
