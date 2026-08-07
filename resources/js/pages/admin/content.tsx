import { Head } from '@inertiajs/react';
import { useState } from 'react';

import { Btn } from '@/components/sdpc/btn';
import { Textarea } from '@/components/sdpc/input';

type Draft = {
    announcements: string;
    rules: string;
    policies: string;
};

const EMPTY: Draft = { announcements: '', rules: '', policies: '' };

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/**
 * Content management.
 *
 * The three fields are edited and previewed live, but there is no content table
 * to save them into yet — that schema is not part of this merge, so nothing is
 * invented here and the notice below says so plainly.
 */
export default function AdminContent() {
    const [draft, setDraft] = useState<Draft>(EMPTY);

    const update = (key: keyof Draft, value: string) =>
        setDraft((current) => ({ ...current, [key]: value }));

    return (
        <div style={{ maxWidth: 1180, margin: '0 auto', padding: '30px 32px 72px' }}>
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
                <div className="card elev-sm" style={{ padding: 20, gap: 16 }}>
                    <EditorField
                        label="Announcements"
                        placeholder="e.g. Milestone escrow is now live for extension payments…"
                        value={draft.announcements}
                        onChange={(value) => update('announcements', value)}
                    />
                    <EditorField
                        label="Platform rules"
                        placeholder="e.g. Projects must be scoped in writing before collaboration begins…"
                        value={draft.rules}
                        onChange={(value) => update('rules', value)}
                    />
                    <EditorField
                        label="System policies"
                        placeholder="e.g. Student accounts require school verification within 14 days…"
                        value={draft.policies}
                        onChange={(value) => update('policies', value)}
                    />

                    <div style={{ display: 'flex', gap: 10 }}>
                        <Btn variant="primary" disabled>
                            Save changes
                        </Btn>
                        <Btn variant="secondary" disabled>
                            Preview as user
                        </Btn>
                    </div>

                    <p
                        style={{
                            margin: 0,
                            padding: '11px 13px',
                            borderRadius: 'var(--radius-md)',
                            background: MUTED(5),
                            fontSize: 12,
                            lineHeight: 1.6,
                            color: MUTED(65),
                        }}
                    >
                        <b style={{ color: 'var(--color-text)' }}>
                            Not persisted yet.
                        </b>{' '}
                        These three fields need a table of their own before they
                        can survive a refresh. That schema sits outside this
                        merge, so the save button stays disabled rather than
                        pretending to work.
                    </p>
                </div>

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
    label,
    placeholder,
    value,
    onChange,
}: {
    label: string;
    placeholder: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="field">
            <label>{label}</label>
            <Textarea
                value={value}
                placeholder={placeholder}
                onChange={(event) => onChange(event.target.value)}
                style={{ minHeight: 88 }}
            />
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
