/*
 * The same format check as App\Rules\SchoolEmailAddress: a mailbox, one or
 * more hostname labels, then `.edu.ph` and nothing after it. So `x@edu.ph`,
 * `x@stiedu.ph` and `x@sti.edu.ph.example.com` all fail, as they do on the
 * server. A format check only — it says nothing about which schools are real.
 */
export const SCHOOL_EMAIL =
    /^[^@\s]+@(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+edu\.ph$/i;

export const SCHOOL_EMAIL_MESSAGE =
    'Use your school email. It must end in .edu.ph.';

/** The format problem with a typed school email, or '' when there is none. */
export const schoolEmailProblem = (value: string): string =>
    value.trim() === '' || SCHOOL_EMAIL.test(value.trim())
        ? ''
        : SCHOOL_EMAIL_MESSAGE;

/**
 * Whether a typed address is on a domain a school actually issues.
 *
 * EXACT match on the part after the last @, lowercased — never endsWith and
 * never includes. A suffix test would tick for anybody who registers any
 * .edu.ph domain, and a substring test would tick for
 * "someone@sti.edu.ph.example.com". The server applies the same rule in
 * School::forEmailDomain(); this is the same question asked early enough to be
 * useful.
 */
export const isListedSchoolEmail = (
    value: string,
    schoolDomains: string[],
): boolean => {
    const at = value.lastIndexOf('@');

    if (at === -1) {
        return false;
    }

    return schoolDomains.includes(
        value
            .slice(at + 1)
            .trim()
            .toLowerCase(),
    );
};
