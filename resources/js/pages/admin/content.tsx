import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import { Textarea } from '@/components/sdpc/input';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/admin/content';

type Draft = {
    announcements: string;
    rules: string;
    policies: string;
};

type Props = {
    content?: Partial<Record<keyof Draft, string | null>>;
};

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/**
 * Content management.
 *
 * The three blocks are edited on the left and previewed live on the right. The
 * preview reads the draft rather than the saved copy, so it shows what saving
 * would publish, not what is already published.
 */
export default function AdminContent({ content }: Props) {
    const [draft, setDraft] = useState<Draft>({
        announcements: content?.announcements ?? '',
        rules: content?.rules ?? '',
        policies: content?.policies ?? '',
    });

    const edit = (key: keyof Draft, value: string) =>
        setDraft((current) => ({ ...current, [key]: value }));

    return (
        <div
            style={{
                maxWidth: 1180,
                margin: '0 auto',
                padding: '30px 32px 72px',
            }}
        >
            <Head title="Content management" />

            <h3 style={{ margin: '0 0 20px' }}>Content management</h3>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1.4fr 1fr',
                    gap: 16,
                    alignItems: 'start',
                }}
            >
                <Form
                    {...update.form()}
                    disableWhileProcessing
                    className="card elev-sm"
                    style={{ padding: 20, gap: 16 }}
                >
                    {({ processing, errors, recentlySuccessful }) => (
                        <>
                            <EditorField
                                name="announcements"
                                label="Announcements"
                                placeholder="e.g. Milestone escrow is now live for extension payments…"
                                value={draft.announcements}
                                error={errors.announcements}
                                onChange={(value) =>
                                    edit('announcements', value)
                                }
                            />
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
                                onChange={(value) => edit('policies', value)}
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

                <div className="card elev-sm" style={{ padding: 20, gap: 12 }}>
                    <h6 style={{ margin: 0 }}>Live preview</h6>
                    <Preview
                        label="Announcement"
                        value={draft.announcements}
                        accent
                    />
                    <Preview label="Rules" value={draft.rules} />
                    <Preview label="Policies" value={draft.policies} />
                </div>
            </div>
        </div>
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

function Preview({
    label,
    value,
    accent = false,
}: {
    label: string;
    value: string;
    accent?: boolean;
}) {
    return (
        <div
            style={{
                padding: '11px 13px',
                borderRadius: 'var(--radius-md)',
                background: accent
                    ? 'color-mix(in srgb, var(--color-accent) 12%, transparent)'
                    : MUTED(5),
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
