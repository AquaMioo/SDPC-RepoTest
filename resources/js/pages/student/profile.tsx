import { Head, router, useForm } from '@inertiajs/react';
import {
    CircleIcon,
    PencilSimpleIcon,
    SealCheckIcon,
    UserIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import Field from '@/components/sdpc/field';
import { Input, Select, Textarea } from '@/components/sdpc/input';
import { Panel } from '@/components/sdpc/panel';
import SkillInput from '@/components/sdpc/skill-input';
import { Tag } from '@/components/sdpc/tag';
import { useCurrentTeam } from '@/hooks/use-current-team';
import {
    destroy as portfolioDestroy,
    store as portfolioStore,
    update as portfolioUpdate,
} from '@/routes/student/portfolio';
import { update as profileUpdate } from '@/routes/student/profile';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

type PortfolioItem = {
    id: number;
    title: string;
    role: string | null;
    description: string | null;
    year: number | null;
    url: string | null;
    repositoryUrl: string | null;
    isFeatured: boolean;
    skills: string[];
};

type Props = {
    profile: {
        name: string;
        headline: string | null;
        biography: string | null;
        location: string | null;
        schoolId: number | null;
        courseId: number | null;
        yearLevel: number | null;
        educationStartedOn: string | null;
        educationNote: string | null;
        githubUrl: string | null;
        portfolioUrl: string | null;
        isAvailable: boolean;
        weeklyHours: number | null;
        availabilityNote: string | null;
        responseTimeHours: number | null;
        hourlyRate: number | null;
        skills: string[];
    };
    portfolio: PortfolioItem[];
    options: {
        schools: { id: number; name: string }[];
        courses: { id: number; name: string; abbreviation: string | null }[];
        skills: { name: string; type: string }[];
    };
    /** Badge only — nothing on the platform is gated on it. */
    isVerifiedStudent: boolean;
};

/**
 * The student's own profile — the screen the client-facing profile reads from.
 *
 * The mockup shows this as a finished page with pencil affordances beside each
 * block; here those blocks are the form itself, because a student with nothing
 * filled in needs somewhere to start rather than an empty page to admire.
 */
export default function StudentProfilePage({
    profile,
    portfolio,
    options,
    isVerifiedStudent,
}: Props) {
    const team = useCurrentTeam();

    const form = useForm({
        headline: profile.headline ?? '',
        biography: profile.biography ?? '',
        location: profile.location ?? '',
        school_id: profile.schoolId?.toString() ?? '',
        course_id: profile.courseId?.toString() ?? '',
        year_level: profile.yearLevel?.toString() ?? '',
        education_started_on: profile.educationStartedOn ?? '',
        education_note: profile.educationNote ?? '',
        github_url: profile.githubUrl ?? '',
        portfolio_url: profile.portfolioUrl ?? '',
        is_available: profile.isAvailable,
        weekly_hours: profile.weeklyHours?.toString() ?? '',
        availability_note: profile.availabilityNote ?? '',
        response_time_hours: profile.responseTimeHours?.toString() ?? '',
        hourly_rate: profile.hourlyRate?.toString() ?? '',
        skills: profile.skills,
    });

    const save = () => {
        form.transform((data) => ({
            ...data,
            school_id: data.school_id || null,
            course_id: data.course_id || null,
            year_level: data.year_level || null,
            education_started_on: data.education_started_on || null,
            weekly_hours: data.weekly_hours || null,
            response_time_hours: data.response_time_hours || null,
            hourly_rate: data.hourly_rate || null,
        }));

        form.patch(profileUpdate.url(team.slug), { preserveScroll: true });
    };

    return (
        <>
            <Head title="My profile" />

            <div
                style={{
                    maxWidth: 1060,
                    margin: '0 auto',
                    padding: '24px 32px 72px',
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-end',
                        gap: 20,
                        marginBottom: 26,
                    }}
                >
                    <span
                        style={{
                            width: 104,
                            height: 104,
                            borderRadius: '50%',
                            background: 'var(--color-accent-800)',
                            color: 'var(--color-accent-200)',
                            display: 'grid',
                            placeItems: 'center',
                            fontSize: 44,
                            flex: 'none',
                        }}
                    >
                        <UserIcon />
                    </span>

                    <div style={{ paddingBottom: 6, marginRight: 'auto' }}>
                        <h3 style={{ margin: 0 }}>{profile.name}</h3>
                        <div style={{ fontSize: 13, color: MUTED(68) }}>
                            {[
                                'Student developer',
                                profile.headline,
                                profile.location,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </div>
                    </div>

                    {isVerifiedStudent && (
                        <Tag variant="accent" style={{ marginBottom: 10 }}>
                            <SealCheckIcon style={{ marginRight: 5 }} />
                            Verified student
                        </Tag>
                    )}
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '270px minmax(0,1fr)',
                        gap: 28,
                        alignItems: 'start',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 18,
                        }}
                    >
                        <div>
                            <h6 style={{ margin: '0 0 8px' }}>About me</h6>
                            <Textarea
                                aria-label="About me"
                                value={form.data.biography}
                                maxLength={5000}
                                placeholder="What you build, and who you build it for."
                                onChange={(event) =>
                                    form.setData('biography', event.target.value)
                                }
                            />
                            <InputError
                                message={form.errors.biography}
                                className="mt-1 text-[11px]"
                            />
                        </div>

                        <Panel style={{ padding: 16, gap: 12 }}>
                            <h6 style={{ margin: 0 }}>Technical arsenal</h6>
                            <SkillInput
                                value={form.data.skills}
                                suggestions={options.skills}
                                onChange={(skills) =>
                                    form.setData('skills', skills)
                                }
                            />
                            <InputError
                                message={form.errors.skills}
                                className="text-[11px]"
                            />
                        </Panel>

                        <Panel style={{ padding: 16, gap: 10 }}>
                            <h6 style={{ margin: 0 }}>Availability</h6>

                            <label
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    fontSize: 12.5,
                                    cursor: 'pointer',
                                }}
                            >
                                <input
                                    type="checkbox"
                                    style={{
                                        accentColor: 'var(--color-accent)',
                                        width: 16,
                                        height: 16,
                                    }}
                                    checked={form.data.is_available}
                                    onChange={(event) =>
                                        form.setData(
                                            'is_available',
                                            event.target.checked,
                                        )
                                    }
                                />
                                <CircleIcon
                                    weight="fill"
                                    style={{
                                        fontSize: 8,
                                        color: form.data.is_available
                                            ? 'var(--color-accent)'
                                            : MUTED(40),
                                    }}
                                />
                                Open to a project this term
                            </label>

                            <Field
                                label="Availability note"
                                error={form.errors.availability_note}
                            >
                                {(props) => (
                                    <Input
                                        {...props}
                                        value={form.data.availability_note}
                                        maxLength={255}
                                        placeholder="Open to one project this term"
                                        onChange={(event) =>
                                            form.setData(
                                                'availability_note',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>

                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr',
                                    gap: 10,
                                }}
                            >
                                <Field
                                    label="Hrs/week"
                                    error={form.errors.weekly_hours}
                                >
                                    {(props) => (
                                        <Input
                                            {...props}
                                            type="number"
                                            min={1}
                                            max={80}
                                            value={form.data.weekly_hours}
                                            placeholder="20"
                                            onChange={(event) =>
                                                form.setData(
                                                    'weekly_hours',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>

                                <Field
                                    label="₱/hr"
                                    error={form.errors.hourly_rate}
                                >
                                    {(props) => (
                                        <Input
                                            {...props}
                                            type="number"
                                            min={0}
                                            value={form.data.hourly_rate}
                                            placeholder="260"
                                            onChange={(event) =>
                                                form.setData(
                                                    'hourly_rate',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>
                            </div>

                            <Field
                                label="Responds within (hours)"
                                error={form.errors.response_time_hours}
                            >
                                {(props) => (
                                    <Input
                                        {...props}
                                        type="number"
                                        min={1}
                                        max={168}
                                        value={form.data.response_time_hours}
                                        placeholder="3"
                                        onChange={(event) =>
                                            form.setData(
                                                'response_time_hours',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                        </Panel>
                    </div>

                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 22,
                        }}
                    >
                        <Panel style={{ padding: 16, gap: 12 }}>
                            <h6 style={{ margin: 0 }}>How you introduce yourself</h6>

                            <Field
                                label="Headline"
                                hint="One line a client reads first, e.g. Full-stack developer · Laravel and React."
                                error={form.errors.headline}
                            >
                                {(props) => (
                                    <Input
                                        {...props}
                                        value={form.data.headline}
                                        maxLength={255}
                                        onChange={(event) =>
                                            form.setData(
                                                'headline',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>

                            <Field
                                label="Location"
                                error={form.errors.location}
                            >
                                {(props) => (
                                    <Input
                                        {...props}
                                        value={form.data.location}
                                        maxLength={255}
                                        placeholder="Towerville, San Jose del Monte"
                                        onChange={(event) =>
                                            form.setData(
                                                'location',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>

                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr',
                                    gap: 12,
                                }}
                            >
                                <Field
                                    label="GitHub"
                                    error={form.errors.github_url}
                                >
                                    {(props) => (
                                        <Input
                                            {...props}
                                            type="url"
                                            value={form.data.github_url}
                                            placeholder="https://github.com/you"
                                            onChange={(event) =>
                                                form.setData(
                                                    'github_url',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>

                                <Field
                                    label="Portfolio site"
                                    error={form.errors.portfolio_url}
                                >
                                    {(props) => (
                                        <Input
                                            {...props}
                                            type="url"
                                            value={form.data.portfolio_url}
                                            placeholder="https://"
                                            onChange={(event) =>
                                                form.setData(
                                                    'portfolio_url',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>
                            </div>
                        </Panel>

                        <Panel style={{ padding: 16, gap: 12 }}>
                            <h6 style={{ margin: 0 }}>Academic background</h6>

                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr',
                                    gap: 12,
                                }}
                            >
                                <Field
                                    label="School"
                                    error={form.errors.school_id}
                                >
                                    {(props) => (
                                        <Select
                                            {...props}
                                            value={form.data.school_id}
                                            onChange={(event) =>
                                                form.setData(
                                                    'school_id',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                Not stated
                                            </option>
                                            {options.schools.map((school) => (
                                                <option
                                                    key={school.id}
                                                    value={school.id}
                                                >
                                                    {school.name}
                                                </option>
                                            ))}
                                        </Select>
                                    )}
                                </Field>

                                <Field
                                    label="Course"
                                    error={form.errors.course_id}
                                >
                                    {(props) => (
                                        <Select
                                            {...props}
                                            value={form.data.course_id}
                                            onChange={(event) =>
                                                form.setData(
                                                    'course_id',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                Not stated
                                            </option>
                                            {options.courses.map((course) => (
                                                <option
                                                    key={course.id}
                                                    value={course.id}
                                                >
                                                    {course.name}
                                                </option>
                                            ))}
                                        </Select>
                                    )}
                                </Field>

                                <Field
                                    label="Year level"
                                    error={form.errors.year_level}
                                >
                                    {(props) => (
                                        <Input
                                            {...props}
                                            type="number"
                                            min={1}
                                            max={6}
                                            value={form.data.year_level}
                                            onChange={(event) =>
                                                form.setData(
                                                    'year_level',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>

                                <Field
                                    label="Started"
                                    error={form.errors.education_started_on}
                                >
                                    {(props) => (
                                        <Input
                                            {...props}
                                            type="date"
                                            value={
                                                form.data.education_started_on
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'education_started_on',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>
                            </div>

                            <Field
                                label="Specialisation"
                                error={form.errors.education_note}
                            >
                                {(props) => (
                                    <Textarea
                                        {...props}
                                        value={form.data.education_note}
                                        maxLength={2000}
                                        placeholder="Specialised in web systems development; capstone track on AI-assisted client matching."
                                        onChange={(event) =>
                                            form.setData(
                                                'education_note',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                        </Panel>

                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                            }}
                        >
                            <span
                                style={{
                                    fontSize: 11.5,
                                    color: MUTED(68),
                                    marginRight: 'auto',
                                }}
                            >
                                Clients read this profile when they shortlist.
                            </span>
                            <Btn
                                variant="primary"
                                disabled={form.processing}
                                onClick={save}
                            >
                                {form.processing ? 'Saving…' : 'Save profile'}
                            </Btn>
                        </div>

                        <PortfolioSection items={portfolio} teamSlug={team.slug} />
                    </div>
                </div>
            </div>
        </>
    );
}

/**
 * Featured portfolio — the Student Background History from the vision
 * document, kept by the student rather than inferred from their projects.
 */
function PortfolioSection({
    items,
    teamSlug,
}: {
    items: PortfolioItem[];
    teamSlug: string;
}) {
    const [editingId, setEditingId] = useState<number | 'new' | null>(null);

    return (
        <div>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    marginBottom: 4,
                }}
            >
                <h6 style={{ margin: 0, marginRight: 'auto' }}>
                    Featured portfolio
                </h6>
                {editingId !== 'new' && (
                    <Btn onClick={() => setEditingId('new')}>Add work</Btn>
                )}
            </div>
            <p
                style={{
                    fontSize: 12.5,
                    color: MUTED(68),
                    margin: '0 0 16px',
                }}
            >
                A showcase of recent development projects and technical
                contributions.
            </p>

            <div
                style={{ display: 'flex', flexDirection: 'column', gap: 16 }}
            >
                {editingId === 'new' && (
                    <PortfolioForm
                        teamSlug={teamSlug}
                        onDone={() => setEditingId(null)}
                    />
                )}

                {items.length === 0 && editingId !== 'new' && (
                    <Panel style={{ padding: 16, gap: 6 }}>
                        <span style={{ fontSize: 13 }}>
                            Nothing here yet.
                        </span>
                        <span style={{ fontSize: 12.5, color: MUTED(65) }}>
                            Capstone work, coursework and anything you have
                            shipped. This is what a client reads before they
                            shortlist you.
                        </span>
                    </Panel>
                )}

                {items.map((item) =>
                    editingId === item.id ? (
                        <PortfolioForm
                            key={item.id}
                            item={item}
                            teamSlug={teamSlug}
                            onDone={() => setEditingId(null)}
                        />
                    ) : (
                        <Panel
                            key={item.id}
                            style={{ padding: 16, gap: 6 }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                }}
                            >
                                <span
                                    style={{
                                        fontFamily: 'var(--font-heading)',
                                        fontSize: 16,
                                    }}
                                >
                                    {item.title}
                                </span>
                                {item.role && (
                                    <Tag variant="neutral">{item.role}</Tag>
                                )}
                                {item.year && (
                                    <span
                                        style={{
                                            fontSize: 11.5,
                                            color: MUTED(68),
                                        }}
                                    >
                                        {item.year}
                                    </span>
                                )}
                                <Btn
                                    icon
                                    variant="bare"
                                    aria-label={`Edit ${item.title}`}
                                    style={{ marginLeft: 'auto' }}
                                    onClick={() => setEditingId(item.id)}
                                >
                                    <PencilSimpleIcon />
                                </Btn>
                            </div>

                            {item.description && (
                                <p
                                    style={{
                                        margin: 0,
                                        fontSize: 12.5,
                                        lineHeight: 1.55,
                                        color: MUTED(60),
                                    }}
                                >
                                    {item.description}
                                </p>
                            )}

                            <div
                                style={{
                                    display: 'flex',
                                    gap: 6,
                                    flexWrap: 'wrap',
                                    marginTop: 2,
                                }}
                            >
                                {item.skills.map((skill) => (
                                    <Tag key={skill} variant="outline">
                                        {skill}
                                    </Tag>
                                ))}
                            </div>
                        </Panel>
                    ),
                )}
            </div>
        </div>
    );
}

function PortfolioForm({
    item,
    teamSlug,
    onDone,
}: {
    item?: PortfolioItem;
    teamSlug: string;
    onDone: () => void;
}) {
    const form = useForm({
        title: item?.title ?? '',
        role: item?.role ?? '',
        description: item?.description ?? '',
        year: item?.year?.toString() ?? '',
        url: item?.url ?? '',
        repository_url: item?.repositoryUrl ?? '',
        is_featured: item?.isFeatured ?? true,
        skills: item?.skills ?? [],
    });

    const submit = () => {
        form.transform((data) => ({ ...data, year: data.year || null }));

        const options = { preserveScroll: true, onSuccess: onDone };

        if (item) {
            form.patch(
                portfolioUpdate.url({
                    current_team: teamSlug,
                    portfolioItem: item.id,
                }),
                options,
            );

            return;
        }

        form.post(portfolioStore.url(teamSlug), options);
    };

    const remove = () => {
        if (!item) {
            return;
        }

        router.delete(
            portfolioDestroy.url({
                current_team: teamSlug,
                portfolioItem: item.id,
            }),
            { preserveScroll: true, onSuccess: onDone },
        );
    };

    return (
        <Panel style={{ padding: 16, gap: 12 }}>
            <Field label="Title" error={form.errors.title} required>
                {(props) => (
                    <Input
                        {...props}
                        value={form.data.title}
                        maxLength={255}
                        placeholder="Grosync"
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                    />
                )}
            </Field>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 120px',
                    gap: 12,
                }}
            >
                <Field label="Your role" error={form.errors.role}>
                    {(props) => (
                        <Input
                            {...props}
                            value={form.data.role}
                            maxLength={120}
                            placeholder="Lead developer"
                            onChange={(event) =>
                                form.setData('role', event.target.value)
                            }
                        />
                    )}
                </Field>

                <Field label="Year" error={form.errors.year}>
                    {(props) => (
                        <Input
                            {...props}
                            type="number"
                            min={2000}
                            value={form.data.year}
                            onChange={(event) =>
                                form.setData('year', event.target.value)
                            }
                        />
                    )}
                </Field>
            </div>

            <Field label="What it does" error={form.errors.description}>
                {(props) => (
                    <Textarea
                        {...props}
                        value={form.data.description}
                        maxLength={2000}
                        placeholder="A web-based inventory system with predictive reorder analytics for grocery stores in San Jose del Monte."
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                    />
                )}
            </Field>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 12,
                }}
            >
                <Field label="Live link" error={form.errors.url}>
                    {(props) => (
                        <Input
                            {...props}
                            type="url"
                            value={form.data.url}
                            placeholder="https://"
                            onChange={(event) =>
                                form.setData('url', event.target.value)
                            }
                        />
                    )}
                </Field>

                <Field
                    label="Repository"
                    error={form.errors.repository_url}
                >
                    {(props) => (
                        <Input
                            {...props}
                            type="url"
                            value={form.data.repository_url}
                            placeholder="https://github.com/you/project"
                            onChange={(event) =>
                                form.setData(
                                    'repository_url',
                                    event.target.value,
                                )
                            }
                        />
                    )}
                </Field>
            </div>

            <div className="field">
                <label>Built with</label>
                <SkillInput
                    value={form.data.skills}
                    onChange={(skills) => form.setData('skills', skills)}
                    placeholder="Laravel, MySQL…"
                />
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                {item && (
                    <Btn
                        variant="ghost"
                        style={{ marginRight: 'auto' }}
                        onClick={remove}
                    >
                        Remove
                    </Btn>
                )}
                <span style={{ marginRight: item ? undefined : 'auto' }} />
                <Btn onClick={onDone}>Cancel</Btn>
                <Btn
                    variant="primary"
                    disabled={form.processing}
                    onClick={submit}
                >
                    {form.processing ? 'Saving…' : 'Save'}
                </Btn>
            </div>
        </Panel>
    );
}
