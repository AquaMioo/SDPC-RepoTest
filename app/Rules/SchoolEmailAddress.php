<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * An address on a Philippine school domain: `<mailbox>@<school>.edu.ph`.
 *
 * A FORMAT check for the sign up form, and nothing more. It admits any school
 * under .edu.ph — including one nobody has put on the schools list — so it
 * must never be what grants a verified student. That gate matches domains
 * exactly against the schools table (School::forEmailDomain(); see
 * .ai/rules/verification.md).
 *
 * The domain is matched label by label and must END in `.edu.ph`, so the
 * lookalikes a plain suffix or substring test lets through fail here:
 * `x@edu.ph` (no school label), `x@stiedu.ph`, `x@sti.edu.ph.example.com`.
 * resources/js/pages/auth/register.tsx asks the same question in the browser.
 */
class SchoolEmailAddress implements ValidationRule
{
    /**
     * A mailbox, one or more hostname labels, then `edu.ph` and nothing after.
     */
    private const PATTERN = '/^[^@\s]+@(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+edu\.ph$/i';

    /**
     * Determine if the given address is on a school domain.
     */
    public static function matches(string $email): bool
    {
        $email = trim($email);

        return preg_match(self::PATTERN, $email) === 1
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::matches($value)) {
            $fail(__('Use your school email. It must end in .edu.ph.'));
        }
    }
}
