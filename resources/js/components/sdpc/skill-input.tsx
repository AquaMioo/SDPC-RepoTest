import { XIcon } from '@phosphor-icons/react';
import { useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Tag } from '@/components/sdpc/tag';
import { Input } from '@/components/ui/input';

type SkillInputProps = {
    value: string[];
    onChange: (skills: string[]) => void;
    /** Existing skills offered as datalist suggestions. */
    suggestions?: { name: string }[];
    placeholder?: string;
    id?: string;
    'aria-invalid'?: boolean;
};

/**
 * The design's skill tagger — chips above a free-text input that commits on
 * Enter or comma.
 */
export default function SkillInput({
    value,
    onChange,
    suggestions = [],
    placeholder = 'Type a skill and press enter',
    id,
    ...props
}: SkillInputProps) {
    const [draft, setDraft] = useState('');
    const listId = id ? `${id}-suggestions` : undefined;

    const commit = (raw: string) => {
        const skill = raw.trim();

        if (!skill) {
            return;
        }

        /** Case-insensitive so "laravel" and "Laravel" do not both appear. */
        const alreadyAdded = value.some(
            (existing) => existing.toLowerCase() === skill.toLowerCase(),
        );

        if (!alreadyAdded) {
            onChange([...value, skill]);
        }

        setDraft('');
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            commit(draft);

            return;
        }

        /** Backspace on an empty input removes the last chip. */
        if (event.key === 'Backspace' && draft === '' && value.length > 0) {
            onChange(value.slice(0, -1));
        }
    };

    return (
        <div>
            {value.length > 0 && (
                <div className="mb-2 flex flex-wrap gap-1.5">
                    {value.map((skill) => (
                        <Tag key={skill} variant="accent">
                            {skill}
                            <button
                                type="button"
                                onClick={() =>
                                    onChange(value.filter((s) => s !== skill))
                                }
                                aria-label={`Remove ${skill}`}
                                className="cursor-pointer hover:text-foreground"
                            >
                                <XIcon />
                            </button>
                        </Tag>
                    ))}
                </div>
            )}

            <Input
                id={id}
                list={listId}
                value={draft}
                placeholder={placeholder}
                onChange={(event) => setDraft(event.target.value)}
                onKeyDown={handleKeyDown}
                onBlur={() => commit(draft)}
                {...props}
            />

            {listId && suggestions.length > 0 && (
                <datalist id={listId}>
                    {suggestions.map((skill) => (
                        <option key={skill.name} value={skill.name} />
                    ))}
                </datalist>
            )}
        </div>
    );
}
