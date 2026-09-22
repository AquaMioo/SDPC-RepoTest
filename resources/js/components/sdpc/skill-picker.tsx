import { XCircleIcon, XIcon } from '@phosphor-icons/react';
import { useMemo, useState } from 'react';
import type { KeyboardEvent } from 'react';

import { Tag } from '@/components/sdpc/tag';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

type SkillPickerProps = {
    value: string[];
    onChange: (skills: string[]) => void;
    /** The technologies a student may pick from — nothing else is accepted. */
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
 * Technologies only, picked from the list. Free text used to be accepted on
 * Enter, which is how "Microsoft Word" reached profiles; now Enter takes the
 * closest technology on the list, and a word that matches none says so rather
 * than being added. The server refuses anything off the list either way.
 */
export default function SkillPicker({
    value,
    onChange,
    suggestions,
    max,
    id,
}: SkillPickerProps) {
    const [draft, setDraft] = useState('');
    const [notOnList, setNotOnList] = useState<string | null>(null);

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
        const typed = raw.trim();

        if (typed === '' || full) {
            return;
        }

        /* Only what is on the list, spelled the way the list spells it. */
        const skill = suggestions.find(
            (each) => each.name.toLowerCase() === typed.toLowerCase(),
        )?.name;

        if (skill === undefined) {
            setNotOnList(typed);

            return;
        }

        setNotOnList(null);

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
                        {/*
                         * The ✕ that takes a skill back off the list. It used
                         * to inherit the chip's own colour and had no hover,
                         * so it read as punctuation rather than a control and
                         * students reported being unable to delete a skill at
                         * all (QA 2026-09-20). Its states live on
                         * button[data-chip-remove] in nocturne.css, because an
                         * inline colour cannot answer :hover.
                         */}
                        <button
                            type="button"
                            onClick={() =>
                                onChange(value.filter((s) => s !== skill))
                            }
                            data-chip-remove=""
                            title={`Remove ${skill}`}
                            aria-label={`Remove ${skill}`}
                        >
                            <XIcon />
                        </button>
                    </Tag>
                ))}

                <input
                    id={id}
                    value={draft}
                    disabled={full}
                    placeholder={full ? '' : 'Add a technology'}
                    onChange={(event) => {
                        setDraft(event.target.value);
                        setNotOnList(null);
                    }}
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
                    : `Languages, frameworks, databases and tools. Maximum ${max} skills.`}
            </div>

            {notOnList !== null && (
                <div
                    role="alert"
                    style={{
                        marginTop: 6,
                        fontSize: 11.5,
                        color: 'var(--destructive)',
                    }}
                >
                    “{notOnList}” is not on the list. Only technologies can be
                    added — pick one of the suggestions.
                </div>
            )}

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
