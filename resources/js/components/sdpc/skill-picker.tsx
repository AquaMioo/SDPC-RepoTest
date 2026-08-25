import { XCircleIcon, XIcon } from '@phosphor-icons/react';
import { useMemo, useState } from 'react';
import type { KeyboardEvent } from 'react';

import { Tag } from '@/components/sdpc/tag';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

type SkillPickerProps = {
    value: string[];
    onChange: (skills: string[]) => void;
    /** The catalogue typing is matched against. */
    suggestions: { name: string }[];
    /** The ceiling the server enforces, mirrored here so it can be said. */
    max: number;
    id?: string;
};

/**
 * The skill picker the "Edit skills" dialog uses.
 *
 * Distinct from SkillInput, which the portfolio still uses: that one leans on
 * a native <datalist>, which the browser draws however it likes and which
 * cannot show that a skill is already picked or that the ceiling is reached.
 * This draws its own list so all three are visible in the same place.
 *
 * Free text is still allowed on Enter. The catalogue is a convenience, not a
 * gate — a student whose skill is not on the list is not thereby without it.
 */
export default function SkillPicker({
    value,
    onChange,
    suggestions,
    max,
    id,
}: SkillPickerProps) {
    const [draft, setDraft] = useState('');

    const full = value.length >= max;

    const matches = useMemo(() => {
        const query = draft.trim().toLowerCase();

        if (query === '') {
            return [];
        }

        const already = new Set(value.map((skill) => skill.toLowerCase()));

        return suggestions
            .filter(
                (skill) =>
                    skill.name.toLowerCase().includes(query) &&
                    !already.has(skill.name.toLowerCase()),
            )
            .slice(0, 6);
    }, [draft, suggestions, value]);

    const commit = (raw: string) => {
        const skill = raw.trim();

        if (skill === '' || full) {
            return;
        }

        /* Case-insensitive, so "laravel" and "Laravel" are not both listed. */
        const already = value.some(
            (existing) => existing.toLowerCase() === skill.toLowerCase(),
        );

        if (!already) {
            onChange([...value, skill]);
        }

        setDraft('');
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            commit(matches[0]?.name ?? draft);

            return;
        }

        /* Backspace on an empty box takes the last chip back. */
        if (event.key === 'Backspace' && draft === '' && value.length > 0) {
            onChange(value.slice(0, -1));
        }
    };

    return (
        <div>
            <div
                className="input"
                style={{
                    display: 'flex',
                    flexWrap: 'wrap',
                    alignItems: 'center',
                    gap: 6,
                    height: 'auto',
                    minHeight: 38,
                    paddingBlock: 6,
                }}
            >
                {value.map((skill) => (
                    <Tag key={skill} variant="neutral">
                        {skill}
                        <button
                            type="button"
                            onClick={() =>
                                onChange(value.filter((s) => s !== skill))
                            }
                            aria-label={`Remove ${skill}`}
                            style={{
                                cursor: 'pointer',
                                background: 'none',
                                border: 0,
                                padding: 0,
                                marginLeft: 4,
                                color: 'inherit',
                            }}
                        >
                            <XIcon />
                        </button>
                    </Tag>
                ))}

                <input
                    id={id}
                    value={draft}
                    disabled={full}
                    placeholder={full ? '' : 'Add a skill'}
                    onChange={(event) => setDraft(event.target.value)}
                    onKeyDown={handleKeyDown}
                    style={{
                        flex: 1,
                        minWidth: 90,
                        border: 0,
                        outline: 'none',
                        background: 'transparent',
                        color: 'inherit',
                        font: 'inherit',
                    }}
                />

                {draft !== '' && (
                    <button
                        type="button"
                        onClick={() => setDraft('')}
                        aria-label="Clear"
                        style={{
                            cursor: 'pointer',
                            background: 'none',
                            border: 0,
                            padding: 0,
                            color: MUTED(45),
                            display: 'grid',
                            placeItems: 'center',
                        }}
                    >
                        <XCircleIcon size={16} />
                    </button>
                )}
            </div>

            <div style={{ marginTop: 6, fontSize: 11.5, color: MUTED(55) }}>
                {full
                    ? `That is the maximum of ${max} skills. Remove one to add another.`
                    : `Maximum ${max} skills.`}
            </div>

            {matches.length > 0 && (
                <ul
                    style={{
                        listStyle: 'none',
                        margin: '10px 0 0',
                        padding: 6,
                        border: '1px solid var(--color-divider)',
                        borderRadius: 'var(--radius-md)',
                        maxHeight: 190,
                        overflowY: 'auto',
                    }}
                >
                    {matches.map((skill) => (
                        <li key={skill.name}>
                            <button
                                type="button"
                                onClick={() => commit(skill.name)}
                                style={{
                                    display: 'block',
                                    width: '100%',
                                    textAlign: 'left',
                                    padding: '8px 10px',
                                    borderRadius: 'var(--radius-sm)',
                                    background: 'none',
                                    border: 0,
                                    cursor: 'pointer',
                                    color: 'inherit',
                                    font: 'inherit',
                                    fontSize: 13,
                                }}
                            >
                                {skill.name}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
