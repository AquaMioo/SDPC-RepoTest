import { CheckCircleIcon } from '@phosphor-icons/react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { Input } from '@/components/sdpc/input';
import { isListedSchoolEmail, schoolEmailProblem } from '@/lib/school-email';

type Props = {
    value: string;
    onChange: (value: string) => void;
    /** Domains schools actually issue addresses on, for the tick. */
    schoolDomains?: string[];
    /** The server's error for this field, if any. */
    error?: string;
    /** Shown but not editable: a code or Microsoft already proved it. */
    readOnly?: boolean;
    id?: string;
    name?: string;
    label?: string;
    tabIndex?: number;
    autoFocus?: boolean;
};

/**
 * The school email box: `.edu.ph` only, with a tick for a school we know.
 *
 * Used on both sign up steps and on the code sign in, so all three refuse the
 * same addresses with the same words before the server is even asked.
 */
export default function SchoolEmailField({
    value,
    onChange,
    schoolDomains = [],
    error,
    readOnly = false,
    id = 'school_email',
    name = 'school_email',
    label = 'School email',
    tabIndex,
    autoFocus,
}: Props) {
    const [touched, setTouched] = useState(false);
    const formatError = schoolEmailProblem(value);

    return (
        <div className="field">
            <label htmlFor={id}>{label}</label>
            <div className="relative">
                <Input
                    id={id}
                    name={name}
                    type="email"
                    required
                    tabIndex={tabIndex}
                    autoFocus={autoFocus}
                    autoComplete="email"
                    placeholder="surname.123456@sjdelmonte.sti.edu.ph"
                    value={value}
                    onChange={(event) => {
                        onChange(event.target.value);
                        /*
                         * The browser refuses to submit while this is set,
                         * with the same words the server would use.
                         */
                        event.target.setCustomValidity(
                            schoolEmailProblem(event.target.value),
                        );
                    }}
                    onBlur={() => setTouched(true)}
                    aria-invalid={Boolean((touched && formatError) || error)}
                    readOnly={readOnly}
                    style={{
                        paddingRight: 32,
                        ...(readOnly
                            ? { opacity: 0.75, cursor: 'not-allowed' }
                            : {}),
                    }}
                />
                {/*
                 * The tick is a courtesy, not the check. It lights for a
                 * domain on the schools list; the server decides the rest.
                 */}
                {isListedSchoolEmail(value, schoolDomains) && (
                    <CheckCircleIcon
                        weight="fill"
                        aria-label="Recognised school email"
                        style={{
                            position: 'absolute',
                            right: 9,
                            top: '50%',
                            transform: 'translateY(-50%)',
                            color: 'var(--color-accent)',
                            fontSize: 16,
                        }}
                    />
                )}
            </div>
            <InputError
                message={(touched && formatError) || error}
                className="mt-1 text-[11px]"
            />
        </div>
    );
}
