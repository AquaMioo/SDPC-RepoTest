import { Form, router } from '@inertiajs/react';
import { ImageIcon } from '@phosphor-icons/react';
import { useRef, useState } from 'react';
import type { ReactNode } from 'react';

import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import { Input, Select, Textarea } from '@/components/sdpc/input';
import SkillPicker from '@/components/sdpc/skill-picker';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { update as accountUpdate } from '@/routes/profile';
import {
    destroy as educationDestroy,
    store as educationStore,
    update as educationUpdate,
} from '@/routes/student/education';
import {
    destroy as languageDestroy,
    store as languageStore,
    update as languageUpdate,
} from '@/routes/student/languages';
import {
    destroy as photoDestroy,
    update as photoUpdate,
} from '@/routes/student/photo';
import { update as profileUpdate } from '@/routes/student/profile';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

export type Education = {
    id: number;
    school: string;
    courseId: number | null;
    areaOfStudy: string | null;
    fromYear: number | null;
    toYear: number | null;
    description: string | null;
    qualification: string | null;
    years: string | null;
};

export type Language = {
    id: number;
    name: string;
    proficiency: string;
    proficiencyLabel: string;
};

type Option = { value: string; label: string };

/**
 * A dialog whose open state the caller owns.
 *
 * Every section on the profile is edited in one of these rather than through a
 * single page-wide save, so each one posts only its own fields — see the note
 * on skills in StudentProfileController::update for what that costs if you
 * forget it.
 */
function Shell({
    open,
    onOpenChange,
    title,
    description,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>

                {children}
            </DialogContent>
        </Dialog>
    );
}

/**
 * "Change profile photo".
 *
 * Not an Inertia <Form>: the file is chosen by click or by drop and has to be
 * held in state until Save, which a plain form input cannot express. The post
 * carries the file itself, so Inertia sends multipart.
 */
export function PhotoDialog({
    open,
    onOpenChange,
    avatarUrl,
    hasPhoto,
    maximumMegabytes,
    minimumPixels,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    avatarUrl: string | null;
    hasPhoto: boolean;
    maximumMegabytes: number;
    minimumPixels: number;
}) {
    const team = useCurrentTeam();
    const [file, setFile] = useState<File | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const input = useRef<HTMLInputElement>(null);

    /*
     * Made once per chosen file and handed back when it is replaced or the
     * dialog closes. Building it inline during render would mint a fresh
     * blob URL on every keystroke elsewhere in the dialog and leak all of
     * them, since nothing would ever revoke the previous one.
     */
    const [preview, setPreview] = useState<string | null>(null);

    const choose = (chosen: File | null) => {
        setPreview((previous) => {
            if (previous !== null) {
                URL.revokeObjectURL(previous);
            }

            return chosen === null ? null : URL.createObjectURL(chosen);
        });

        setFile(chosen);
        setError(null);
    };

    const close = () => {
        choose(null);
        onOpenChange(false);
    };

    const save = () => {
        if (file === null) {
            setError('Choose a photo first.');

            return;
        }

        setBusy(true);

        router.post(
            photoUpdate.url(team.slug),
            { photo: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: close,
                onError: (errors) =>
                    setError(errors.photo ?? 'That photo could not be saved.'),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Shell
            open={open}
            onOpenChange={(next) => (next ? onOpenChange(true) : close())}
            title="Change profile photo"
        >
            <div style={{ display: 'flex', gap: 16, alignItems: 'flex-start' }}>
                <button
                    type="button"
                    onClick={() => input.current?.click()}
                    style={{
                        width: 96,
                        height: 96,
                        flex: 'none',
                        borderRadius: '50%',
                        border: '1px dashed var(--color-divider)',
                        background: 'transparent',
                        color: MUTED(55),
                        fontSize: 11,
                        cursor: 'pointer',
                        display: 'grid',
                        placeItems: 'center',
                        overflow: 'hidden',
                        padding: 0,
                    }}
                >
                    {(preview ?? avatarUrl) ? (
                        <img
                            src={preview ?? avatarUrl ?? undefined}
                            alt=""
                            style={{
                                width: '100%',
                                height: '100%',
                                objectFit: 'cover',
                            }}
                        />
                    ) : (
                        <span>
                            Preview
                            <br />
                            or browse files
                        </span>
                    )}
                </button>

                <p
                    style={{
                        margin: 0,
                        fontSize: 12.5,
                        lineHeight: 1.6,
                        color: MUTED(65),
                    }}
                >
                    A square photo works best. Your face should fill most of the
                    frame — clients see this beside every message and proposal.
                </p>
            </div>

            <div
                onDragOver={(event) => event.preventDefault()}
                onDrop={(event) => {
                    event.preventDefault();
                    const dropped = event.dataTransfer.files?.[0];

                    if (dropped) {
                        choose(dropped);
                    }
                }}
                onClick={() => input.current?.click()}
                style={{
                    marginTop: 14,
                    padding: '26px 16px',
                    textAlign: 'center',
                    border: '1px dashed var(--color-divider)',
                    borderRadius: 'var(--radius-md)',
                    cursor: 'pointer',
                }}
            >
                <ImageIcon
                    size={22}
                    style={{ color: MUTED(45), margin: '0 auto 8px' }}
                />
                <div style={{ fontSize: 12.5 }}>
                    {file ? file.name : 'Upload a photo or drag and drop'}
                </div>
                <div style={{ marginTop: 4, fontSize: 11, color: MUTED(50) }}>
                    JPG or PNG, at least {minimumPixels}×{minimumPixels}, up to{' '}
                    {maximumMegabytes} MB
                </div>

                <input
                    ref={input}
                    type="file"
                    accept="image/*"
                    hidden
                    onChange={(event) =>
                        choose(event.target.files?.[0] ?? null)
                    }
                />
            </div>

            <InputError message={error ?? undefined} className="mt-2" />

            <DialogFooter className="gap-3">
                {/* Only offered when there is something to take down. */}
                {hasPhoto && (
                    <Btn
                        type="button"
                        variant="ghost"
                        style={{ marginRight: 'auto', fontSize: 12.5 }}
                        onClick={() =>
                            router.delete(photoDestroy.url(team.slug), {
                                preserveScroll: true,
                                onSuccess: close,
                            })
                        }
                    >
                        Remove photo
                    </Btn>
                )}

                <Btn type="button" variant="ghost" onClick={close}>
                    Cancel
                </Btn>

                <Btn
                    type="button"
                    variant="secondary"
                    disabled={busy}
                    onClick={save}
                    data-test="save-photo-button"
                >
                    {busy && <Spinner />}
                    Save
                </Btn>
            </DialogFooter>
        </Shell>
    );
}

/**
 * "Add education", and the same dialog editing one.
 */
export function EducationDialog({
    open,
    onOpenChange,
    education,
    courses,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Null when adding. */
    education: Education | null;
    courses: { id: number; name: string; abbreviation: string | null }[];
}) {
    const team = useCurrentTeam();
    const editing = education !== null;
    const thisYear = new Date().getFullYear();

    /* Far enough back for a parent returning to study, and a decade ahead. */
    const years = Array.from({ length: 61 }, (_, i) => thisYear + 10 - i);

    return (
        <Shell
            open={open}
            onOpenChange={onOpenChange}
            title={editing ? 'Edit education' : 'Add education'}
        >
            <Form
                key={`${String(open)}-${education?.id ?? 'new'}`}
                {...(editing
                    ? educationUpdate.form([team.slug, education.id])
                    : educationStore.form(team.slug))}
                options={{ preserveScroll: true }}
                onSuccess={() => onOpenChange(false)}
            >
                {({ processing, errors }) => (
                    <>
                        <div className="field">
                            <label htmlFor="school">School</label>
                            <Input
                                id="school"
                                name="school"
                                required
                                defaultValue={education?.school ?? ''}
                                placeholder="Ex: STI College San Jose Del Monte"
                                aria-invalid={Boolean(errors.school)}
                            />
                            <InputError
                                message={errors.school}
                                className="mt-1 text-[11px]"
                            />
                        </div>

                        <div className="field" style={{ marginTop: 12 }}>
                            <label htmlFor="from_year">
                                Dates attended{' '}
                                <span style={{ color: MUTED(50) }}>
                                    (optional)
                                </span>
                            </label>
                            <div style={{ display: 'flex', gap: 10 }}>
                                <Select
                                    id="from_year"
                                    name="from_year"
                                    defaultValue={education?.fromYear ?? ''}
                                    style={{ flex: 1 }}
                                >
                                    <option value="">From</option>
                                    {years.map((year) => (
                                        <option key={year} value={year}>
                                            {year}
                                        </option>
                                    ))}
                                </Select>

                                <Select
                                    name="to_year"
                                    aria-label="To (or expected graduation year)"
                                    defaultValue={education?.toYear ?? ''}
                                    style={{ flex: 1 }}
                                >
                                    <option value="">
                                        To (or expected graduation year)
                                    </option>
                                    {years.map((year) => (
                                        <option key={year} value={year}>
                                            {year}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                            <InputError
                                message={errors.to_year ?? errors.from_year}
                                className="mt-1 text-[11px]"
                            />
                        </div>

                        <div className="field" style={{ marginTop: 12 }}>
                            <label htmlFor="course_id">
                                Degree{' '}
                                <span style={{ color: MUTED(50) }}>
                                    (optional)
                                </span>
                            </label>
                            <Select
                                id="course_id"
                                name="course_id"
                                defaultValue={education?.courseId ?? ''}
                            >
                                <option value="">Degree (optional)</option>
                                {courses.map((course) => (
                                    <option key={course.id} value={course.id}>
                                        {course.abbreviation
                                            ? `${course.abbreviation} — ${course.name}`
                                            : course.name}
                                    </option>
                                ))}
                            </Select>
                            <InputError
                                message={errors.course_id}
                                className="mt-1 text-[11px]"
                            />
                        </div>

                        <div className="field" style={{ marginTop: 12 }}>
                            <label htmlFor="area_of_study">
                                Area of study{' '}
                                <span style={{ color: MUTED(50) }}>
                                    (optional)
                                </span>
                            </label>
                            <Input
                                id="area_of_study"
                                name="area_of_study"
                                defaultValue={education?.areaOfStudy ?? ''}
                                placeholder="Ex: Computer Science"
                            />
                            <InputError
                                message={errors.area_of_study}
                                className="mt-1 text-[11px]"
                            />
                        </div>

                        <div className="field" style={{ marginTop: 12 }}>
                            <label htmlFor="description">
                                Description{' '}
                                <span style={{ color: MUTED(50) }}>
                                    (optional)
                                </span>
                            </label>
                            <Textarea
                                id="description"
                                name="description"
                                rows={4}
                                defaultValue={education?.description ?? ''}
                            />
                            <InputError
                                message={errors.description}
                                className="mt-1 text-[11px]"
                            />
                        </div>

                        <DialogFooter className="mt-4 gap-3">
                            {editing && (
                                <Btn
                                    type="button"
                                    variant="ghost"
                                    style={{
                                        marginRight: 'auto',
                                        fontSize: 12.5,
                                    }}
                                    onClick={() =>
                                        router.delete(
                                            educationDestroy.url([
                                                team.slug,
                                                education.id,
                                            ]),
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    onOpenChange(false),
                                            },
                                        )
                                    }
                                >
                                    Delete
                                </Btn>
                            )}

                            <DialogClose asChild>
                                <Btn type="button" variant="ghost">
                                    Cancel
                                </Btn>
                            </DialogClose>

                            <Btn
                                type="submit"
                                variant="secondary"
                                disabled={processing}
                                data-test="save-education-button"
                            >
                                {processing && <Spinner />}
                                Save
                            </Btn>
                        </DialogFooter>
                    </>
                )}
            </Form>
        </Shell>
    );
}

/**
 * One language, added or edited.
 */
export function LanguageDialog({
    open,
    onOpenChange,
    language,
    proficiencies,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Null when adding. */
    language: Language | null;
    proficiencies: Option[];
}) {
    const team = useCurrentTeam();
    const editing = language !== null;

    return (
        <Shell
            open={open}
            onOpenChange={onOpenChange}
            title={editing ? 'Edit language' : 'Add language'}
        >
            <Form
                key={`${String(open)}-${language?.id ?? 'new'}`}
                {...(editing
                    ? languageUpdate.form([team.slug, language.id])
                    : languageStore.form(team.slug))}
                options={{ preserveScroll: true }}
                onSuccess={() => onOpenChange(false)}
            >
                {({ processing, errors }) => (
                    <>
                        <div className="field">
                            <label htmlFor="language-name">Language</label>
                            <Input
                                id="language-name"
                                name="name"
                                required
                                defaultValue={language?.name ?? ''}
                                placeholder="Ex: Tagalog"
                                aria-invalid={Boolean(errors.name)}
                            />
                            <InputError
                                message={errors.name}
                                className="mt-1 text-[11px]"
                            />
                        </div>

                        <div className="field" style={{ marginTop: 12 }}>
                            <label htmlFor="proficiency">Proficiency</label>
                            <Select
                                id="proficiency"
                                name="proficiency"
                                required
                                defaultValue={language?.proficiency ?? ''}
                            >
                                <option value="" disabled>
                                    Choose a level
                                </option>
                                {proficiencies.map((level) => (
                                    <option
                                        key={level.value}
                                        value={level.value}
                                    >
                                        {level.label}
                                    </option>
                                ))}
                            </Select>
                            <InputError
                                message={errors.proficiency}
                                className="mt-1 text-[11px]"
                            />
                        </div>

                        <DialogFooter className="mt-4 gap-3">
                            {editing && (
                                <Btn
                                    type="button"
                                    variant="ghost"
                                    style={{
                                        marginRight: 'auto',
                                        fontSize: 12.5,
                                    }}
                                    onClick={() =>
                                        router.delete(
                                            languageDestroy.url([
                                                team.slug,
                                                language.id,
                                            ]),
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    onOpenChange(false),
                                            },
                                        )
                                    }
                                >
                                    Delete
                                </Btn>
                            )}

                            <DialogClose asChild>
                                <Btn type="button" variant="ghost">
                                    Cancel
                                </Btn>
                            </DialogClose>

                            <Btn
                                type="submit"
                                variant="secondary"
                                disabled={processing}
                                data-test="save-language-button"
                            >
                                {processing && <Spinner />}
                                Save
                            </Btn>
                        </DialogFooter>
                    </>
                )}
            </Form>
        </Shell>
    );
}

/**
 * "Edit skills" — the picker, and nothing else.
 *
 * Posts only `skills`, which the controller now checks for by key rather than
 * syncing whatever it finds; otherwise every other dialog on this page would
 * quietly empty the list.
 */
export function SkillsDialog({
    open,
    onOpenChange,
    skills,
    catalogue,
    maximum,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    skills: string[];
    catalogue: { name: string }[];
    maximum: number;
}) {
    const team = useCurrentTeam();
    const [chosen, setChosen] = useState(skills);
    const [busy, setBusy] = useState(false);

    return (
        <Shell
            open={open}
            onOpenChange={(next) => {
                /* Reopening starts from what is saved, not from a stale edit. */
                if (next) {
                    setChosen(skills);
                }

                onOpenChange(next);
            }}
            title="Edit skills"
        >
            <div className="field">
                <label htmlFor="skills">Your skills</label>
                <SkillPicker
                    id="skills"
                    value={chosen}
                    onChange={setChosen}
                    suggestions={catalogue}
                    max={maximum}
                />
            </div>

            <DialogFooter className="gap-3">
                <DialogClose asChild>
                    <Btn type="button" variant="ghost">
                        Cancel
                    </Btn>
                </DialogClose>

                <Btn
                    type="button"
                    variant="secondary"
                    disabled={busy}
                    data-test="save-skills-button"
                    onClick={() => {
                        setBusy(true);

                        router.patch(
                            profileUpdate.url(team.slug),
                            { skills: chosen },
                            {
                                preserveScroll: true,
                                onSuccess: () => onOpenChange(false),
                                onFinish: () => setBusy(false),
                            },
                        );
                    }}
                >
                    {busy && <Spinner />}
                    Save
                </Btn>
            </DialogFooter>
        </Shell>
    );
}

/**
 * The headline and the paragraphs under it — the card a client reads first.
 */
export function AboutDialog({
    open,
    onOpenChange,
    headline,
    biography,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    headline: string | null;
    biography: string | null;
}) {
    const team = useCurrentTeam();

    return (
        <Shell
            open={open}
            onOpenChange={onOpenChange}
            title="Edit your introduction"
            description="One line a client reads first, then the detail behind it."
        >
            <Form
                key={String(open)}
                {...profileUpdate.form(team.slug)}
                options={{ preserveScroll: true }}
                onSuccess={() => onOpenChange(false)}
            >
                {({ processing, errors }) => (
                    <>
                        <div className="field">
                            <label htmlFor="headline">Headline</label>
                            <Input
                                id="headline"
                                name="headline"
                                defaultValue={headline ?? ''}
                                placeholder="Ex: Laravel and React developer · 4th year BSIT"
                            />
                            <InputError
                                message={errors.headline}
                                className="mt-1 text-[11px]"
                            />
                        </div>

                        <div className="field" style={{ marginTop: 12 }}>
                            <label htmlFor="biography">About you</label>
                            <Textarea
                                id="biography"
                                name="biography"
                                rows={7}
                                defaultValue={biography ?? ''}
                                placeholder="What you build, who you build it for, and how you work."
                            />
                            <InputError
                                message={errors.biography}
                                className="mt-1 text-[11px]"
                            />
                        </div>

                        <DialogFooter className="mt-4 gap-3">
                            <DialogClose asChild>
                                <Btn type="button" variant="ghost">
                                    Cancel
                                </Btn>
                            </DialogClose>

                            <Btn
                                type="submit"
                                variant="secondary"
                                disabled={processing}
                                data-test="save-about-button"
                            >
                                {processing && <Spinner />}
                                Save
                            </Btn>
                        </DialogFooter>
                    </>
                )}
            </Form>
        </Shell>
    );
}

/**
 * "Account" — the person, not the profile.
 *
 * Name and email live on the user row, not on student_profiles, and the
 * settings screen no longer carries an editor for them. Without this dialog a
 * student would have no way to correct their own name or change the address
 * they sign in with, anywhere on the platform.
 *
 * The picture is deliberately not here. It has its own dialog on the avatar,
 * which is where somebody looks for it.
 */
export function AccountDialog({
    open,
    onOpenChange,
    name,
    email,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    name: string;
    email: string;
}) {
    return (
        <Shell
            open={open}
            onOpenChange={onOpenChange}
            title="Account"
            description="The name clients see, and the address you sign in with."
        >
            <Form
                key={String(open)}
                {...accountUpdate.form()}
                options={{ preserveScroll: true }}
                onSuccess={() => onOpenChange(false)}
            >
                {({ processing, errors }) => (
                    <>
                        <div className="field">
                            <label htmlFor="account-name">Full name</label>
                            <Input
                                id="account-name"
                                name="name"
                                required
                                autoComplete="name"
                                defaultValue={name}
                                aria-invalid={Boolean(errors.name)}
                            />
                            <InputError
                                message={errors.name}
                                className="mt-1 text-[11px]"
                            />
                        </div>

                        <div className="field" style={{ marginTop: 12 }}>
                            <label htmlFor="account-email">Email</label>
                            <Input
                                id="account-email"
                                name="email"
                                type="email"
                                required
                                autoComplete="username"
                                defaultValue={email}
                                aria-invalid={Boolean(errors.email)}
                            />
                            <InputError
                                message={errors.email}
                                className="mt-1 text-[11px]"
                            />
                            <p
                                style={{
                                    margin: '6px 0 0',
                                    fontSize: 11,
                                    color: MUTED(55),
                                }}
                            >
                                Changing this asks you to confirm the new
                                address before it is trusted again.
                            </p>
                        </div>

                        <DialogFooter className="mt-4 gap-3">
                            <DialogClose asChild>
                                <Btn type="button" variant="ghost">
                                    Cancel
                                </Btn>
                            </DialogClose>

                            <Btn
                                type="submit"
                                variant="secondary"
                                disabled={processing}
                                data-test="save-account-button"
                            >
                                {processing && <Spinner />}
                                Save
                            </Btn>
                        </DialogFooter>
                    </>
                )}
            </Form>
        </Shell>
    );
}
