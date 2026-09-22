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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
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
        isAvailable: boolean;
        weeklyHours: number | null;
        availabilityNote: string | null;
        responseTimeHours: number | null;
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
    const [photoOpen, setPhotoOpen] = useState(false);
    const [accountOpen, setAccountOpen] = useState(false);
    const [skillsOpen, setSkillsOpen] = useState(false);
    /* School, course and year level — the three filterable columns. */
    const [enrolmentOpen, setEnrolmentOpen] = useState(false);
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [education, setEducation] = useState<Education | null>(null);
    const [educationOpen, setEducationOpen] = useState(false);
    const [language, setLanguage] = useState<Language | null>(null);
    const [languageOpen, setLanguageOpen] = useState(false);

    /*
     * Always on. The "Public view" preview toggle was removed on the panel's
     * instruction — this screen is the owner's own profile and every card
     * carries its pencil. The shared cards below still accept an `editable`
     * prop because they are reused, so the page hands them a constant rather
     * than the prop being threaded away.
     */
    const editable = true;

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
                data-motion=""
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
                                onPhoto
                                style={{
                                    position: 'absolute',
                                    right: -4,
                                    bottom: -4,
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
                            onEdit={() => setEnrolmentOpen(true)}
                            addLabel="Add a school you attended"
                            editLabel="Edit school, course and year level"
                        >
                            {/*
                             * What the student is enrolled in now, above the
                             * list of where they have studied. These three are
                             * one per profile and are what RecruitController
                             * filters on; the rows below are the readable
                             * history.
                             */}
                            {(profile.schoolName ??
                                profile.courseAbbreviation ??
                                profile.yearLevel) !== null && (
                                <div
                                    style={{
                                        display: 'grid',
                                        gap: 2,
                                        paddingBottom: 10,
                                        borderBottom: `1px solid ${MUTED(12)}`,
                                    }}
                                >
                                    <Enrolment
                                        label="School"
                                        value={profile.schoolName}
                                    />
                                    <Enrolment
                                        label="Course"
                                        value={profile.courseAbbreviation}
                                    />
                                    <Enrolment
                                        label="Year level"
                                        value={
                                            profile.yearLevel
                                                ? `${profile.yearLevel}${ordinal(profile.yearLevel)} year`
                                                : null
                                        }
                                    />
                                </div>
                            )}

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
                        <DescriptionCard
                            editable={editable}
                            headline={profile.headline}
                            biography={profile.biography}
                        />

                        <Panel
                            style={{ padding: 20, gap: 12 }}
                            className="profile-card"
                        >
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

                        <Panel
                            style={{ padding: 20, gap: 10 }}
                            className="profile-card"
                        >
                            <Heading
                                editable={editable}
                                onEdit={() => setDetailsOpen(true)}
                                label="Edit your links"
                            >
                                Links
                            </Heading>

                            {profile.githubUrl === null ? (
                                <Empty>No links yet.</Empty>
                            ) : (
                                <ExternalLink
                                    label="GitHub"
                                    href={profile.githubUrl}
                                />
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

            <EnrolmentDialog
                open={enrolmentOpen}
                onOpenChange={setEnrolmentOpen}
                profile={profile}
                options={options}
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

/**
 * "Description" — the headline and the paragraphs under it, the card a client
 * reads first.
 *
 * Edited in place: the pencil turns the card itself into the form, so a
 * student writes their introduction where it will be read rather than in a
 * dialog over it. It posts only these two fields.
 */
function DescriptionCard({
    editable,
    headline,
    biography,
}: {
    editable: boolean;
    headline: string | null;
    biography: string | null;
}) {
    const team = useCurrentTeam();
    const [editing, setEditing] = useState(false);

    const form = useForm({
        headline: headline ?? '',
        biography: biography ?? '',
    });

    const start = () => {
        /* Always from what is saved, not from an abandoned edit. */
        form.setData({ headline: headline ?? '', biography: biography ?? '' });
        form.clearErrors();
        setEditing(true);
    };

    const save = () =>
        form.patch(profileUpdate.url(team.slug), {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });

    return (
        <Panel
            style={{ padding: 20, gap: 12 }}
            className="profile-card"
            data-editing={editing ? '' : undefined}
        >
            <Heading
                editable={editable && !editing}
                onEdit={start}
                label="Edit your description"
            >
                Description
            </Heading>

            {editing ? (
                <form
                    className="profile-inline-form"
                    onSubmit={(event) => {
                        event.preventDefault();
                        save();
                    }}
                    style={{ display: 'grid', gap: 12 }}
                >
                    <div className="field">
                        <label htmlFor="headline">Headline</label>
                        <Input
                            id="headline"
                            value={form.data.headline}
                            maxLength={255}
                            autoFocus
                            placeholder="Ex: Laravel and React developer · 4th year BSIT"
                            onChange={(event) =>
                                form.setData('headline', event.target.value)
                            }
                        />
                        <InputError
                            message={form.errors.headline}
                            className="mt-1 text-[11px]"
                        />
                    </div>

                    <div className="field">
                        <label htmlFor="biography">About you</label>
                        <Textarea
                            id="biography"
                            rows={7}
                            maxLength={5000}
                            value={form.data.biography}
                            placeholder="What you build, who you build it for, and how you work."
                            onChange={(event) =>
                                form.setData('biography', event.target.value)
                            }
                        />
                        <InputError
                            message={form.errors.biography}
                            className="mt-1 text-[11px]"
                        />
                    </div>

                    <div
                        style={{
                            display: 'flex',
                            gap: 8,
                            justifyContent: 'flex-end',
                        }}
                    >
                        <Btn
                            type="button"
                            variant="ghost"
                            disabled={form.processing}
                            onClick={() => setEditing(false)}
                        >
                            Cancel
                        </Btn>
                        <Btn
                            type="submit"
                            variant="secondary"
                            disabled={form.processing}
                            data-test="save-description-button"
                        >
                            Save
                        </Btn>
                    </div>
                </form>
            ) : (
                <>
                    {headline && (
                        <div
                            style={{
                                fontSize: 13,
                                color: 'var(--color-accent)',
                            }}
                        >
                            {headline}
                        </div>
                    )}

                    {biography ? (
                        biography.split(/\n{2,}/).map((paragraph, index) => (
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
                            Say what you build and who you build it for. This is
                            the first thing a client reads.
                        </Empty>
                    )}
                </>
            )}
        </Panel>
    );
}

/** "1st", "2nd", "3rd", "4th" — the list only ever runs to four. */
function ordinal(year: number): string {
    return { 1: 'st', 2: 'nd', 3: 'rd' }[year] ?? 'th';
}

/** The small round pencil the design hangs off every editable section. */
function IconButton({
    label,
    onClick,
    onPhoto = false,
    style,
}: {
    label: string;
    onClick: () => void;
    /** Sits over the avatar, so it needs a solid ground (data-on-photo). */
    onPhoto?: boolean;
    style?: React.CSSProperties;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            data-on-photo={onPhoto ? '' : undefined}
            aria-label={label}
            title={label}
            /* Colours and hover live on button[data-edit-button] in
               nocturne.css; inline they could not react. */
            data-edit-button=""
            style={{
                width: 26,
                height: 26,
                display: 'grid',
                placeItems: 'center',
                borderRadius: '50%',
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
    addLabel,
    editLabel,
    children,
}: {
    title: string;
    editable: boolean;
    onAdd?: () => void;
    onEdit?: () => void;
    /** What the + does, in words. Defaults to "Add <title>". */
    addLabel?: string;
    /** What the pencil does. Defaults to "Edit <title>". */
    editLabel?: string;
    children: ReactNode;
}) {
    return (
        <Panel style={{ padding: 16, gap: 10 }} className="profile-card">
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
                    /*
                     * A real tooltip rather than the browser's `title`, which
                     * takes a second to appear and cannot be styled — somebody
                     * hovering a bare + should be told what it does at once
                     * (QA 2026-09-20). aria-label carries the same words for a
                     * screen reader, and data-edit-button gives it the hover
                     * and press states the pencils already have; inline styles
                     * could not react to :hover.
                     */
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                type="button"
                                onClick={onAdd}
                                aria-label={
                                    addLabel ?? `Add ${title.toLowerCase()}`
                                }
                                data-edit-button=""
                                style={{
                                    width: 26,
                                    height: 26,
                                    display: 'grid',
                                    placeItems: 'center',
                                    borderRadius: '50%',
                                    cursor: 'pointer',
                                    flex: 'none',
                                }}
                            >
                                <PlusIcon size={13} />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>
                            {addLabel ?? `Add ${title.toLowerCase()}`}
                        </TooltipContent>
                    </Tooltip>
                )}

                {editable && onEdit && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span style={{ display: 'inline-flex' }}>
                                <IconButton
                                    label={
                                        editLabel ??
                                        `Edit ${title.toLowerCase()}`
                                    }
                                    onClick={onEdit}
                                />
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>
                            {editLabel ?? `Edit ${title.toLowerCase()}`}
                        </TooltipContent>
                    </Tooltip>
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

/** One of the three enrolment facts, above the Education list. */
function Enrolment({ label, value }: { label: string; value: string | null }) {
    return (
        <div style={{ display: 'flex', gap: 8, fontSize: 12 }}>
            <span style={{ width: 66, flex: 'none', color: MUTED(50) }}>
                {label}
            </span>
            <span style={{ minWidth: 0 }}>
                {value ?? <span style={{ color: MUTED(40) }}>Not stated</span>}
            </span>
        </div>
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
 * What the redesign's cards do not each own: which barangay you are in, and
 * where to read your work.
 *
 * school_id, course_id and year_level used to be here. They moved to
 * EnrolmentDialog, under the Education card, where a student looks for them —
 * they are still the columns RecruitController filters by and still one per
 * profile, so nothing about what they mean has changed, only which pencil
 * opens them.
 *
 * The hours-per-week, replies-in and availability-note fields were taken out
 * (QA 2026-09-20). student_profiles still carries the columns and
 * client/students/show still reads them, so existing answers are shown rather
 * than lost — there is simply no editor for them here any more.
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
        barangay: profile.barangay ?? '',
        github_url: profile.githubUrl ?? '',
        is_available: profile.isAvailable,
    });

    const save = () => {
        form.transform((data) => ({
            ...data,
            /*
             * An empty select posts "", and the rule is nullable rather than
             * letting a blank string past an exists check.
             */
            barangay: data.barangay || null,
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

/**
 * What the student is enrolled in: school, course and year level.
 *
 * These three moved out of "Profile details" and under the Education card
 * (QA 2026-09-20), which is where a student goes looking for them. They are
 * still one per profile and still the columns RecruitController filters on —
 * EducationDialog beside them writes the readable student_educations list,
 * which is a different thing and stays a different dialog. See
 * .ai/rules/student.md.
 */
function EnrolmentDialog({
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
        school_id: profile.schoolId?.toString() ?? '',
        course_id: profile.courseId?.toString() ?? '',
        year_level: profile.yearLevel?.toString() ?? '',
    });

    const save = () => {
        form.transform((data) => ({
            ...data,
            /*
             * Empty selects post "", and each of these rules is nullable
             * rather than allowing a blank string past an exists check.
             */
            school_id: data.school_id || null,
            course_id: data.course_id || null,
            year_level: data.year_level || null,
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
                    <DialogTitle>School, course and year level</DialogTitle>
                </DialogHeader>

                <div style={{ display: 'grid', gap: 12 }}>
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
                        data-test="save-enrolment-button"
                    >
                        Save
                    </Btn>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
