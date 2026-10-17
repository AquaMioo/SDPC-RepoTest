import { Form, router } from '@inertiajs/react';
import { ImageSquareIcon, TrashSimpleIcon, XIcon } from '@phosphor-icons/react';
import { useEffect, useRef, useState } from 'react';

import { AnnouncementCarousel } from '@/components/announcement-carousel';
import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import { Textarea } from '@/components/sdpc/input';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/admin/content';
import {
    destroy as destroyImage,
    store as storeImages,
} from '@/routes/admin/content/images';
import type { SiteContentDraft } from '@/types/admin';

/** A picture already posted as the Announcement. */
export type PostedAnnouncementImage = {
    id: number;
    url: string;
    name: string | null;
};

/** A picture chosen on this screen, previewed before it is posted. */
type PendingImage = {
    key: string;
    file: File;
    url: string;
};

type Props = {
    content?: Partial<Record<keyof SiteContentDraft, string | null>>;
    announcementImages?: PostedAnnouncementImage[];
    announcementImageLimit?: number;
};

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/**
 * Content management.
 *
 * The Announcement is pictures (owner, 2026-10-18): the administrator picks up
 * to ten, sees them before posting, and removes posted ones one at a time. The
 * rules and policies are still copy, edited on the left. Everything is
 * previewed live on the right, from the draft rather than the saved copy, so
 * it shows what saving or posting would publish.
 */
export default function ContentEditor({
    content,
    announcementImages = [],
    announcementImageLimit = 10,
}: Props) {
    const [draft, setDraft] = useState<
        Pick<SiteContentDraft, 'rules' | 'policies'>
    >({
        rules: content?.rules ?? '',
        policies: content?.policies ?? '',
    });
    const [pending, setPending] = useState<PendingImage[]>([]);

    const edit = (key: 'rules' | 'policies', value: string) =>
        setDraft((current) => ({ ...current, [key]: value }));

    return (
        <section style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <div>
                <h6 style={{ margin: 0 }}>Content management</h6>
                <div style={{ fontSize: 12.5, color: MUTED(55) }}>
                    Announcement, platform rules and system policies
                </div>
            </div>

            <div
                className="stack"
                style={{
                    ['--cols' as string]: '1.4fr 1fr',
                    gap: 16,
                    alignItems: 'start',
                }}
            >
                <div style={{ display: 'grid', gap: 16 }}>
                    <AnnouncementImagesField
                        posted={announcementImages}
                        pending={pending}
                        onPendingChange={setPending}
                        limit={announcementImageLimit}
                    />

                    <Form
                        {...update.form()}
                        disableWhileProcessing
                        className="card elev-sm"
                        style={{ padding: 20, gap: 16 }}
                    >
                        {({ processing, errors, recentlySuccessful }) => (
                            <>
                                <EditorField
                                    name="rules"
                                    label="Platform rules"
                                    placeholder="e.g. Projects must be scoped in writing before collaboration begins…"
                                    value={draft.rules}
                                    error={errors.rules}
                                    onChange={(value) => edit('rules', value)}
                                />
                                <EditorField
                                    name="policies"
                                    label="System policies"
                                    placeholder="e.g. Student accounts require school verification within 14 days…"
                                    value={draft.policies}
                                    error={errors.policies}
                                    onChange={(value) =>
                                        edit('policies', value)
                                    }
                                />

                                <div
                                    style={{
                                        display: 'flex',
                                        gap: 10,
                                        alignItems: 'center',
                                    }}
                                >
                                    <Btn
                                        type="submit"
                                        variant="primary"
                                        data-test="save-content-button"
                                    >
                                        {processing && <Spinner />}
                                        Save changes
                                    </Btn>

                                    {recentlySuccessful && (
                                        <span
                                            style={{
                                                fontSize: 12,
                                                color: MUTED(65),
                                            }}
                                            data-test="content-saved"
                                        >
                                            Saved.
                                        </span>
                                    )}
                                </div>
                            </>
                        )}
                    </Form>
                </div>

                <div className="card elev-sm" style={{ padding: 20, gap: 12 }}>
                    <h6 style={{ margin: 0 }}>Live preview</h6>
                    <AnnouncementPreview
                        images={[
                            ...announcementImages.map((image) => ({
                                id: image.id,
                                url: image.url,
                            })),
                            ...pending.map((image) => ({
                                id: image.key,
                                url: image.url,
                            })),
                        ]}
                    />
                    <Preview label="Rules" value={draft.rules} />
                    <Preview label="Policies" value={draft.policies} />
                </div>
            </div>
        </section>
    );
}

/**
 * The Announcement's pictures: the ones posted, and the ones picked on this
 * screen waiting to be posted, each with its own way out.
 */
function AnnouncementImagesField({
    posted,
    pending,
    onPendingChange,
    limit,
}: {
    posted: PostedAnnouncementImage[];
    pending: PendingImage[];
    onPendingChange: (images: PendingImage[]) => void;
    limit: number;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const [removing, setRemoving] = useState<number | null>(null);

    const room = limit - posted.length;
    const overBy = pending.length - room;

    /* Object URLs are released once their preview is gone. */
    const latest = useRef(pending);
    useEffect(() => {
        latest.current = pending;
    }, [pending]);
    useEffect(
        () => () =>
            latest.current.forEach((image) => URL.revokeObjectURL(image.url)),
        [],
    );

    const choose = (files: FileList | null) => {
        if (!files) {
            return;
        }

        onPendingChange([
            ...pending,
            ...Array.from(files).map((file) => ({
                key: `${file.name}-${file.size}-${file.lastModified}-${Math.random()}`,
                file,
                url: URL.createObjectURL(file),
            })),
        ]);
        setErrors({});

        if (input.current) {
            input.current.value = '';
        }
    };

    const drop = (key: string) => {
        const image = pending.find((each) => each.key === key);

        if (image) {
            URL.revokeObjectURL(image.url);
        }

        onPendingChange(pending.filter((each) => each.key !== key));
    };

    const post = () => {
        setBusy(true);
        router.post(
            storeImages.url(),
            { images: pending.map((image) => image.file) },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    pending.forEach((image) => URL.revokeObjectURL(image.url));
                    onPendingChange([]);
                },
                onError: setErrors,
                onFinish: () => setBusy(false),
            },
        );
    };

    const remove = (id: number) => {
        setRemoving(id);
        router.delete(destroyImage.url(id), {
            preserveScroll: true,
            onFinish: () => setRemoving(null),
        });
    };

    const firstError = Object.values(errors)[0];

    return (
        <div
            className="card elev-sm"
            style={{ padding: 20, gap: 14 }}
            data-test="announcement-images"
        >
            <div style={{ display: 'flex', alignItems: 'baseline', gap: 10 }}>
                <span className="field" style={{ marginRight: 'auto' }}>
                    <label htmlFor="announcement-images">Announcement</label>
                </span>
                <span style={{ fontSize: 12, color: MUTED(60) }}>
                    {posted.length + pending.length} of {limit} images
                </span>
            </div>

            {posted.length > 0 && (
                <Thumbnails>
                    {posted.map((image) => (
                        <Thumbnail
                            key={image.id}
                            url={image.url}
                            label={image.name ?? 'Posted image'}
                            note="Posted"
                        >
                            <Btn
                                variant="ghost"
                                icon
                                aria-label="Remove this image from the announcement"
                                disabled={removing === image.id}
                                onClick={() => remove(image.id)}
                                style={{ width: 26, height: 26 }}
                            >
                                {removing === image.id ? (
                                    <Spinner />
                                ) : (
                                    <TrashSimpleIcon />
                                )}
                            </Btn>
                        </Thumbnail>
                    ))}
                </Thumbnails>
            )}

            {pending.length > 0 && (
                <>
                    <div style={{ fontSize: 12, color: MUTED(62) }}>
                        Preview: not posted yet
                    </div>
                    <Thumbnails>
                        {pending.map((image) => (
                            <Thumbnail
                                key={image.key}
                                url={image.url}
                                label={image.file.name}
                                note="New"
                            >
                                <Btn
                                    variant="ghost"
                                    icon
                                    aria-label="Take this image out"
                                    onClick={() => drop(image.key)}
                                    style={{ width: 26, height: 26 }}
                                >
                                    <XIcon />
                                </Btn>
                            </Thumbnail>
                        ))}
                    </Thumbnails>
                </>
            )}

            <input
                ref={input}
                id="announcement-images"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                multiple
                hidden
                onChange={(event) => choose(event.target.files)}
            />

            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 10,
                    flexWrap: 'wrap',
                }}
            >
                <Btn
                    variant="secondary"
                    disabled={room - pending.length <= 0}
                    onClick={() => input.current?.click()}
                >
                    <ImageSquareIcon />
                    Choose images
                </Btn>
                {pending.length > 0 && (
                    <Btn
                        variant="primary"
                        disabled={busy || overBy > 0}
                        onClick={post}
                        data-test="post-announcement-images"
                    >
                        {busy && <Spinner />}
                        Post {pending.length === 1 ? 'image' : 'images'}
                    </Btn>
                )}
                <span style={{ fontSize: 11.5, color: MUTED(55) }}>
                    JPG, PNG or WebP, up to 5 MB each. One image shows still;
                    two or more slide on both dashboards.
                </span>
            </div>

            {overBy > 0 && (
                <InputError
                    message={`An announcement takes at most ${limit} images. Take out ${overBy} before posting.`}
                />
            )}
            <InputError message={firstError} />
        </div>
    );
}

function Thumbnails({ children }: { children: React.ReactNode }) {
    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fill, minmax(112px, 1fr))',
                gap: 10,
            }}
        >
            {children}
        </div>
    );
}

function Thumbnail({
    url,
    label,
    note,
    children,
}: {
    url: string;
    label: string;
    note: string;
    children: React.ReactNode;
}) {
    return (
        <figure
            style={{
                margin: 0,
                display: 'grid',
                gap: 4,
                padding: 6,
                borderRadius: 'var(--radius-md)',
                border: '1px solid var(--color-divider)',
            }}
        >
            <img
                src={url}
                alt={label}
                style={{
                    width: '100%',
                    aspectRatio: '4 / 3',
                    objectFit: 'cover',
                    borderRadius: 'var(--radius-sm)',
                    background: MUTED(5),
                }}
            />
            <figcaption
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 4,
                    fontSize: 11,
                    color: MUTED(60),
                    minWidth: 0,
                }}
            >
                <span
                    style={{
                        marginRight: 'auto',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap',
                    }}
                    title={label}
                >
                    {note}
                </span>
                {children}
            </figcaption>
        </figure>
    );
}

function EditorField({
    name,
    label,
    placeholder,
    value,
    error,
    onChange,
}: {
    name: string;
    label: string;
    placeholder: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="field">
            <label htmlFor={name}>{label}</label>
            <Textarea
                id={name}
                name={name}
                value={value}
                placeholder={placeholder}
                onChange={(event) => onChange(event.target.value)}
                aria-invalid={Boolean(error)}
                style={{ minHeight: 88 }}
            />
            <InputError message={error} className="mt-1 text-[11px]" />
        </div>
    );
}

/** The Announcement as the dashboards will draw it, posted and pending together. */
function AnnouncementPreview({
    images,
}: {
    images: { id: number | string; url: string }[];
}) {
    return (
        <div
            style={{
                padding: '11px 13px',
                borderRadius: 'var(--radius-md)',
                background:
                    'color-mix(in srgb, var(--color-accent) 12%, transparent)',
                fontSize: 12.5,
                lineHeight: 1.5,
                display: 'grid',
                gap: 6,
            }}
        >
            <span className="card-kicker">Announcement</span>
            {images.length === 0 ? (
                <div style={{ color: MUTED(40) }}>Nothing published yet.</div>
            ) : (
                <div style={{ height: 200 }}>
                    <AnnouncementCarousel images={images} />
                </div>
            )}
        </div>
    );
}

function Preview({ label, value }: { label: string; value: string }) {
    return (
        <div
            style={{
                padding: '11px 13px',
                borderRadius: 'var(--radius-md)',
                background: MUTED(5),
                fontSize: 12.5,
                lineHeight: 1.5,
            }}
        >
            <span className="card-kicker">{label}</span>
            <div
                style={{
                    marginTop: 4,
                    whiteSpace: 'pre-wrap',
                    wordBreak: 'break-word',
                    color: value ? undefined : MUTED(40),
                }}
            >
                {value || 'Nothing published yet.'}
            </div>
        </div>
    );
}
