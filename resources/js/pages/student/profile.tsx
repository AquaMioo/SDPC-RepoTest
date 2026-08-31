import { Head, useForm } from '@inertiajs/react';
import {
    MapPinIcon,
    PencilSimpleIcon,
    PlusIcon,
    SealCheckIcon,
    UserIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import { Input, Select, Textarea } from '@/components/sdpc/input';
import { Panel } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import {
    AboutDialog,
    AccountDialog,
    EducationDialog,
    LanguageDialog,
    PhotoDialog,
    SkillsDialog,
} from '@/components/student/profile-dialogs';
import type { Education, Language } from '@/components/student/profile-dialogs';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { update as profileUpdate } from '@/routes/student/profile';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/** College runs four years, so those are the only levels on offer. */
const YEAR_LEVELS = [
    { value: '1', label: '1st year' },
    { value: '2', label: '2nd year' },
    { value: '3', label: '3rd year' },
    { value: '4', label: '4th year' },
];

type Props = {
    profile: {
        name: string;
        email: string;
        avatarUrl: string | null;
        displayLocation: string | null;
        schoolName: string | null;
        courseAbbreviation: string | null;
        headline: string | null;
        biography: string | null;
        location: string | null;
        barangay: string | null;
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
    educations: Education[];
    languages: Language[];
    options: {
        schools: { id: number; name: string }[];
        courses: { id: number; name: string; abbreviation: string | null }[];
        skills: { name: string; type: string }[];
        barangays: string[];
        proficiencies: { value: string; label: string }[];
    };
    maximumSkills: number;
    photoLimits: { megabytes: number; pixels: number };
    isVerifiedStudent: boolean;
};

/**
 * The student's own profile.
 *
 * Edited a section at a time. The page used to be one long form behind a
 * single "Save profile" button, which meant correcting a typo in your headline
 * re-submitted your rate, your availability and every skill you had — and made
 * the page unreadable as a profile, because everything was an input whether or
 * not you were changing it. Now it reads as the thing a client sees, and each
 * card opens its own dialog.
 *
 * "Public view" hides the edit affordances rather than fetching anything: the
 * client-facing screen lives behind EnsureUserIsClient, so a student cannot
 * actually open it, and showing them the same content without the pencils is
 * the honest version of the preview.
 */
export default function StudentProfilePage({
    profile,
    educations,
    languages,
    options,
    maximumSkills,
    photoLimits,
    isVerifiedStudent,
}: Props) {
    const [publicView, setPublicView] = useState(false);
    const [photoOpen, setPhotoOpen] = useState(false);
    const [accountOpen, setAccountOpen] = useState(false);
    const [aboutOpen, setAboutOpen] = useState(false);
    const [skillsOpen, setSkillsOpen] = useState(false);
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [education, setEducation] = useState<Education | null>(null);
    const [educationOpen, setEducationOpen] = useState(false);
    const [language, setLanguage] = useState<Language | null>(null);
    const [languageOpen, setLanguageOpen] = useState(false);

    const editable = !publicView;

    const openEducation = (entry: Education | null) => {
        setEducation(entry);
        setEducationOpen(true);
    };

    const openLanguage = (entry: Language | null) => {
        setLanguage(entry);
        setLanguageOpen(true);
    };

    const studyLine = [
        profile.schoolName,
        [
            profile.courseAbbreviation,
            profile.yearLevel
                ? `${profile.yearLevel}${ordinal(profile.yearLevel)} year`
                : null,
        ]
            .filter(Boolean)
            .join(' '),
    ]
        .filter((part) => part !== null && part !== '')
        .join(' · ');

    return (
        <>
            <Head title="My profile" />

            <div
                style={{
                    maxWidth: 'clamp(1060px, 100vw - 320px, 1600px)',
                    margin: '0 auto',
                    padding: '24px clamp(16px, 4vw, 32px) 72px',
                }}
            >
                <Panel
                    style={{
                        padding: 20,
                        gap: 16,
                        flexDirection: 'row',
                        alignItems: 'flex-start',
                    }}
                >
                    <div style={{ position: 'relative', flex: 'none' }}>
                        <span
                            style={{
                                width: 74,
                                height: 74,
                                borderRadius: '50%',
                                display: 'grid',
                                placeItems: 'center',
                                overflow: 'hidden',
                                background: 'var(--color-accent-200)',
                                color: 'var(--color-accent-700)',
                                border: profile.avatarUrl
                                    ? 'none'
                                    : '1px dashed var(--color-divider)',
                                fontSize: 11,
                                textAlign: 'center',
                                lineHeight: 1.3,
                            }}
                        >
                            {profile.avatarUrl ? (
                                <img
                                    src={profile.avatarUrl}
                                    alt={profile.name}
                                    style={{
                                        width: '100%',
                                        height: '100%',
                                        objectFit: 'cover',
                                    }}
                                />
                            ) : (
                                <UserIcon size={28} />
                            )}
                        </span>

                        {editable && (
                            <IconButton
                                label="Change profile photo"
                                onClick={() => setPhotoOpen(true)}
                                style={{
                                    position: 'absolute',
                                    right: -4,
                                    bottom: -4,
                                    background: 'var(--color-surface)',
                                }}
                            />
                        )}
                    </div>

                    <div style={{ marginRight: 'auto', minWidth: 0 }}>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                            }}
                        >
                            <h3 style={{ margin: 0 }}>{profile.name}</h3>
                            {isVerifiedStudent && (
                                <SealCheckIcon
                                    weight="fill"
                                    aria-label="Verified student"
                                    style={{ color: 'var(--color-accent)' }}
                                />
                            )}
                            {editable && (
                                <IconButton
                                    label="Edit your name and email"
                                    onClick={() => setAccountOpen(true)}
                                />
                            )}
                        </div>

                        {profile.displayLocation && (
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 5,
                                    marginTop: 4,
                                    fontSize: 12.5,
                                    color: MUTED(65),
                                }}
                            >
                                <MapPinIcon />
                                {profile.displayLocation}
                            </div>
                        )}

                        {studyLine && (
                            <div
                                style={{
                                    marginTop: 2,
                                    fontSize: 12.5,
                                    color: 'var(--color-accent)',
                                }}
                            >
                                {studyLine}
                            </div>
                        )}
                    </div>

                    {/*
                     * A preview rather than a link. The client-facing profile
                     * is behind EnsureUserIsClient, so a student opening it
                     * would be turned away by their own role.
                     */}
                    <div style={{ display: 'flex', flex: 'none', gap: 0 }}>
                        <ViewTab
                            active={!publicView}
                            onClick={() => setPublicView(false)}
                        >
                            My view
                        </ViewTab>
                        <ViewTab
                            active={publicView}
                            onClick={() => setPublicView(true)}
                        >
                            Public view
                        </ViewTab>
                    </div>
                </Panel>

                <div
                    className="split"
                    style={{
                        ['--rail' as string]: 'minmax(0, 240px)',
                        gap: 16,
                        marginTop: 16,
                    }}
                >
                    <div style={{ display: 'grid', gap: 16 }}>
                        <Card
                            title="Languages"
                            editable={editable}
                            onAdd={() => openLanguage(null)}
                        >
                            {languages.length === 0 ? (
                                <Empty>No languages listed yet.</Empty>
                            ) : (
                                languages.map((entry) => (
                                    <Row
                                        key={entry.id}
                                        editable={editable}
                                        onEdit={() => openLanguage(entry)}
                                        label={`Edit ${entry.name}`}
                                    >
                                        <span style={{ fontSize: 12.5 }}>
                                            <strong style={{ fontWeight: 500 }}>
                                                {entry.name}:
                                            </strong>{' '}
                                            <span style={{ color: MUTED(65) }}>
                                                {entry.proficiencyLabel}
                                            </span>
                                        </span>
                                    </Row>
                                ))
                            )}
                        </Card>

                        <Card
                            title="Education"
                            editable={editable}
                            onAdd={() => openEducation(null)}
                        >
                            {educations.length === 0 ? (
                                <Empty>No schools listed yet.</Empty>
                            ) : (
                                educations.map((entry) => (
                                    <Row
                                        key={entry.id}
                                        editable={editable}
                                        onEdit={() => openEducation(entry)}
                                        label={`Edit ${entry.school}`}
                                    >
                                        <div style={{ minWidth: 0 }}>
                                            <div style={{ fontSize: 12.5 }}>
                                                {entry.school}
                                            </div>
                                            {entry.qualification && (
                                                <div
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: 'var(--color-accent)',
                                                    }}
                                                >
                                                    {entry.qualification}
                                                </div>
                                            )}
                                            {entry.years && (
                                                <div
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: MUTED(50),
                                                    }}
                                                >
                                                    {entry.years}
                                                </div>
                                            )}
                                        </div>
                                    </Row>
                                ))
                            )}
                        </Card>
                    </div>

                    <div style={{ display: 'grid', gap: 16 }}>
                        <Panel style={{ padding: 20, gap: 12 }}>
                            <Heading
                                editable={editable}
                                onEdit={() => setAboutOpen(true)}
                                label="Edit your introduction"
                            >
                                Student
                            </Heading>

                            {profile.headline && (
                                <div
                                    style={{
                                        fontSize: 13,
                                        color: 'var(--color-accent)',
                                    }}
                                >
                                    {profile.headline}
                                </div>
                            )}

                            {profile.biography ? (
                                profile.biography
                                    .split(/\n{2,}/)
                                    .map((paragraph, index) => (
                                        <p
                                            key={index}
                                            style={{
                                                margin: 0,
                                                fontSize: 13,
                                                lineHeight: 1.7,
                                                color: MUTED(78),
                                            }}
                                        >
                                            {paragraph}
                                        </p>
                                    ))
                            ) : (
                                <Empty>
                                    Say what you build and who you build it for.
                                    This is the first thing a client reads.
                                </Empty>
                            )}
                        </Panel>

                        <Panel style={{ padding: 20, gap: 12 }}>
                            <Heading
                                editable={editable}
                                onEdit={() => setSkillsOpen(true)}
                                label="Edit skills"
                            >
                                Skills
                            </Heading>

                            <span
                                style={{
                                    fontSize: 10,
                                    letterSpacing: '0.1em',
                                    textTransform: 'uppercase',
                                    color: MUTED(50),
                                }}
                            >
                                Self-reported
                            </span>

                            {profile.skills.length === 0 ? (
                                <Empty>No skills listed yet.</Empty>
                            ) : (
                                <div
                                    style={{
                                        display: 'flex',
                                        flexWrap: 'wrap',
                                        gap: 6,
                                    }}
                                >
                                    {profile.skills.map((skill) => (
                                        <Tag key={skill} variant="neutral">
                                            {skill}
                                        </Tag>
                                    ))}
                                </div>
                            )}
                        </Panel>

                        <Panel style={{ padding: 20, gap: 10 }}>
                            <Heading
                                editable={editable}
                                onEdit={() => setDetailsOpen(true)}
                                label="Edit your links"
                            >
                                Links
                            </Heading>

                            {profile.githubUrl === null &&
                            profile.portfolioUrl === null ? (
                                <Empty>No links yet.</Empty>
                            ) : (
                                <>
                                    <ExternalLink
                                        label="GitHub"
                                        href={profile.githubUrl}
                                    />
                                    <ExternalLink
                                        label="Portfolio"
                                        href={profile.portfolioUrl}
                                    />
                                </>
                            )}
                        </Panel>
                    </div>
                </div>
            </div>

            <PhotoDialog
                open={photoOpen}
                onOpenChange={setPhotoOpen}
                avatarUrl={profile.avatarUrl}
                hasPhoto={profile.avatarUrl !== null}
                maximumMegabytes={photoLimits.megabytes}
                minimumPixels={photoLimits.pixels}
            />

            <AccountDialog
                open={accountOpen}
                onOpenChange={setAccountOpen}
                name={profile.name}
                email={profile.email}
            />

            <AboutDialog
                open={aboutOpen}
                onOpenChange={setAboutOpen}
                headline={profile.headline}
                biography={profile.biography}
            />

            <SkillsDialog
                open={skillsOpen}
                onOpenChange={setSkillsOpen}
                skills={profile.skills}
                catalogue={options.skills}
                maximum={maximumSkills}
            />

            <EducationDialog
                open={educationOpen}
                onOpenChange={setEducationOpen}
                education={education}
                courses={options.courses}
            />

            <LanguageDialog
                open={languageOpen}
                onOpenChange={setLanguageOpen}
                language={language}
                proficiencies={options.proficiencies}
            />

            <DetailsDialog
                open={detailsOpen}
                onOpenChange={setDetailsOpen}
                profile={profile}
                options={options}
            />
        </>
    );
}

/** "1st", "2nd", "3rd", "4th" — the list only ever runs to four. */
function ordinal(year: number): string {
    return { 1: 'st', 2: 'nd', 3: 'rd' }[year] ?? 'th';
}

/** One of the two segmented tabs above the profile. */
function ViewTab({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            style={{
                padding: '6px 14px',
                fontSize: 12.5,
                cursor: 'pointer',
                fontFamily: 'var(--font-heading)',
                border: '1px solid var(--color-divider)',
                background: active
                    ? 'color-mix(in srgb, var(--color-accent) 12%, transparent)'
                    : 'transparent',
                color: active ? 'var(--color-accent)' : MUTED(60),
            }}
        >
            {children}
        </button>
    );
}

/** The small round pencil the design hangs off every editable section. */
function IconButton({
    label,
    onClick,
    style,
}: {
    label: string;
    onClick: () => void;
    style?: React.CSSProperties;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            title={label}
            style={{
                width: 26,
                height: 26,
                display: 'grid',
                placeItems: 'center',
                borderRadius: '50%',
                border: '1px solid var(--color-divider)',
                background: 'transparent',
                color: 'var(--color-accent)',
                cursor: 'pointer',
                flex: 'none',
                ...style,
            }}
        >
            <PencilSimpleIcon size={13} />
        </button>
    );
}

/** A left-column card: kicker, an add button, and its rows. */
function Card({
    title,
    editable,
    onAdd,
    onEdit,
    children,
}: {
    title: string;
    editable: boolean;
    onAdd?: () => void;
    onEdit?: () => void;
    children: ReactNode;
}) {
    return (
        <Panel style={{ padding: 16, gap: 10 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                <span
                    style={{
                        marginRight: 'auto',
                        fontSize: 10,
                        letterSpacing: '0.1em',
                        textTransform: 'uppercase',
                        color: MUTED(55),
                    }}
                >
                    {title}
                </span>

                {editable && onAdd && (
                    <button
                        type="button"
                        onClick={onAdd}
                        aria-label={`Add ${title.toLowerCase()}`}
                        title={`Add ${title.toLowerCase()}`}
                        style={{
                            width: 26,
                            height: 26,
                            display: 'grid',
                            placeItems: 'center',
                            borderRadius: '50%',
                            border: '1px solid var(--color-divider)',
                            background: 'transparent',
                            color: 'var(--color-accent)',
                            cursor: 'pointer',
                        }}
                    >
                        <PlusIcon size={13} />
                    </button>
                )}

                {editable && onEdit && (
                    <IconButton
                        label={`Edit ${title.toLowerCase()}`}
                        onClick={onEdit}
                    />
                )}
            </div>

            {children}
        </Panel>
    );
}

/** A row inside a left-column card, with its own pencil. */
function Row({
    editable,
    onEdit,
    label,
    children,
}: {
    editable: boolean;
    onEdit: () => void;
    label: string;
    children: ReactNode;
}) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'flex-start',
                gap: 8,
            }}
        >
            <div style={{ marginRight: 'auto', minWidth: 0 }}>{children}</div>
            {editable && <IconButton label={label} onClick={onEdit} />}
        </div>
    );
}

/** A right-hand heading with an optional pencil beside it. */
function Heading({
    editable,
    onEdit,
    label,
    children,
}: {
    editable: boolean;
    onEdit: () => void;
    label: string;
    children: ReactNode;
}) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <h5 style={{ margin: 0, marginRight: 'auto' }}>{children}</h5>
            {editable && <IconButton label={label} onClick={onEdit} />}
        </div>
    );
}

/** The line a card shows when it has nothing in it yet. */
function Empty({ children }: { children: ReactNode }) {
    return (
        <p
            style={{
                margin: 0,
                fontSize: 12,
                lineHeight: 1.6,
                color: MUTED(50),
            }}
        >
            {children}
        </p>
    );
}

/** One outbound link, absent rather than empty when unset. */
function ExternalLink({ label, href }: { label: string; href: string | null }) {
    if (href === null) {
        return null;
    }

    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            style={{ fontSize: 12.5, wordBreak: 'break-all' }}
        >
            {label}: {href}
        </a>
    );
}

/**
 * Everything the redesign's cards do not each own: where you are, what you are
 * enrolled in, your links and your availability.
 *
 * school_id, course_id and year_level are here rather than in the Education
 * dialog on purpose. That dialog writes the readable list; these three are the
 * columns RecruitController filters students by, so they stay one per profile
 * and keep feeding the line under the name.
 */
function DetailsDialog({
    open,
    onOpenChange,
    profile,
    options,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    profile: Props['profile'];
    options: Props['options'];
}) {
    const team = useCurrentTeam();

    const form = useForm({
        location: profile.location ?? '',
        barangay: profile.barangay ?? '',
        school_id: profile.schoolId?.toString() ?? '',
        course_id: profile.courseId?.toString() ?? '',
        year_level: profile.yearLevel?.toString() ?? '',
        github_url: profile.githubUrl ?? '',
        portfolio_url: profile.portfolioUrl ?? '',
        is_available: profile.isAvailable,
        weekly_hours: profile.weeklyHours?.toString() ?? '',
        availability_note: profile.availabilityNote ?? '',
        response_time_hours: profile.responseTimeHours?.toString() ?? '',
        hourly_rate: profile.hourlyRate?.toString() ?? '',
    });

    const save = () => {
        form.transform((data) => ({
            ...data,
            /*
             * Empty selects post "", and every one of these rules is nullable
             * rather than allowing a blank string past an exists check.
             */
            school_id: data.school_id || null,
            course_id: data.course_id || null,
            year_level: data.year_level || null,
            barangay: data.barangay || null,
            weekly_hours: data.weekly_hours || null,
            response_time_hours: data.response_time_hours || null,
            hourly_rate: data.hourly_rate || null,
        }));

        form.patch(profileUpdate.url(team.slug), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Profile details</DialogTitle>
                </DialogHeader>

                <div
                    style={{
                        display: 'grid',
                        gap: 12,
                        maxHeight: '60vh',
                        overflowY: 'auto',
                    }}
                >
                    <div className="field">
                        <label htmlFor="location">
                            Area or subdivision{' '}
                            <span style={{ color: MUTED(50) }}>(optional)</span>
                        </label>
                        <Input
                            id="location"
                            value={form.data.location}
                            placeholder="Towerville, Phase 2, near the market"
                            onChange={(e) =>
                                form.setData('location', e.target.value)
                            }
                        />
                        <InputError
                            message={form.errors.location}
                            className="mt-1 text-[11px]"
                        />
                    </div>

                    <div className="field">
                        <label htmlFor="barangay">Barangay</label>
                        <Select
                            id="barangay"
                            value={form.data.barangay}
                            onChange={(e) =>
                                form.setData('barangay', e.target.value)
                            }
                        >
                            <option value="">Not stated</option>
                            {options.barangays.map((name) => (
                                <option key={name} value={name}>
                                    {name}
                                </option>
                            ))}
                        </Select>
                        <InputError
                            message={form.errors.barangay}
                            className="mt-1 text-[11px]"
                        />
                    </div>

                    <div className="field">
                        <label htmlFor="school_id">School</label>
                        <Select
                            id="school_id"
                            value={form.data.school_id}
                            onChange={(e) =>
                                form.setData('school_id', e.target.value)
                            }
                        >
                            <option value="">Not stated</option>
                            {options.schools.map((school) => (
                                <option key={school.id} value={school.id}>
                                    {school.name}
                                </option>
                            ))}
                        </Select>
                        <InputError
                            message={form.errors.school_id}
                            className="mt-1 text-[11px]"
                        />
                    </div>

                    <div className="field">
                        <label htmlFor="course_id">Course</label>
                        <Select
                            id="course_id"
                            value={form.data.course_id}
                            onChange={(e) =>
                                form.setData('course_id', e.target.value)
                            }
                        >
                            <option value="">Not stated</option>
                            {options.courses.map((course) => (
                                <option key={course.id} value={course.id}>
                                    {course.name}
                                </option>
                            ))}
                        </Select>
                        <InputError
                            message={form.errors.course_id}
                            className="mt-1 text-[11px]"
                        />
                    </div>

                    <div className="field">
                        <label htmlFor="year_level">Year level</label>
                        <Select
                            id="year_level"
                            value={form.data.year_level}
                            onChange={(e) =>
                                form.setData('year_level', e.target.value)
                            }
                        >
                            <option value="">Not stated</option>
                            {YEAR_LEVELS.map((level) => (
                                <option key={level.value} value={level.value}>
                                    {level.label}
                                </option>
                            ))}
                        </Select>
                        <InputError
                            message={form.errors.year_level}
                            className="mt-1 text-[11px]"
                        />
                    </div>

                    <div className="field">
                        <label htmlFor="github_url">GitHub</label>
                        <Input
                            id="github_url"
                            value={form.data.github_url}
                            placeholder="https://github.com/you"
                            onChange={(e) =>
                                form.setData('github_url', e.target.value)
                            }
                        />
                        <InputError
                            message={form.errors.github_url}
                            className="mt-1 text-[11px]"
                        />
                    </div>

                    <div className="field">
                        <label htmlFor="portfolio_url">Portfolio site</label>
                        <Input
                            id="portfolio_url"
                            value={form.data.portfolio_url}
                            placeholder="https://you.dev"
                            onChange={(e) =>
                                form.setData('portfolio_url', e.target.value)
                            }
                        />
                        <InputError
                            message={form.errors.portfolio_url}
                            className="mt-1 text-[11px]"
                        />
                    </div>

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
                            checked={form.data.is_available}
                            onChange={(e) =>
                                form.setData('is_available', e.target.checked)
                            }
                            style={{
                                accentColor: 'var(--color-accent)',
                                width: 15,
                                height: 15,
                            }}
                        />
                        Open to a project this term
                    </label>

                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fit, minmax(150px, 1fr))',
                            gap: 10,
                        }}
                    >
                        <div className="field">
                            <label htmlFor="weekly_hours">Hrs/week</label>
                            <Input
                                id="weekly_hours"
                                type="number"
                                value={form.data.weekly_hours}
                                onChange={(e) =>
                                    form.setData('weekly_hours', e.target.value)
                                }
                            />
                        </div>

                        <div className="field">
                            <label htmlFor="hourly_rate">₱/hr</label>
                            <Input
                                id="hourly_rate"
                                type="number"
                                value={form.data.hourly_rate}
                                onChange={(e) =>
                                    form.setData('hourly_rate', e.target.value)
                                }
                            />
                        </div>

                        <div className="field">
                            <label htmlFor="response_time_hours">
                                Replies in (h)
                            </label>
                            <Input
                                id="response_time_hours"
                                type="number"
                                value={form.data.response_time_hours}
                                onChange={(e) =>
                                    form.setData(
                                        'response_time_hours',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>

                    <div className="field">
                        <label htmlFor="availability_note">
                            Availability note
                        </label>
                        <Textarea
                            id="availability_note"
                            rows={2}
                            value={form.data.availability_note}
                            onChange={(e) =>
                                form.setData(
                                    'availability_note',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError
                            message={form.errors.availability_note}
                            className="mt-1 text-[11px]"
                        />
                    </div>
                </div>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Btn type="button" variant="ghost">
                            Cancel
                        </Btn>
                    </DialogClose>

                    <Btn
                        type="button"
                        variant="secondary"
                        disabled={form.processing}
                        onClick={save}
                        data-test="save-details-button"
                    >
                        Save
                    </Btn>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
