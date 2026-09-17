<?php

namespace App\Enums;

enum VerificationProvider: string
{
    /**
     * The document an administrator reviews by hand.
     *
     * This is the path that actually grants a verified account, and it existed
     * before any automated check did.
     */
    case Document = 'document';

    /**
     * An address the school itself issues, proved by a mailed code or by the
     * school's own Microsoft sign-in.
     *
     * This one DOES gate: while it is enabled,
     * User::hasPassedStudentVerification() requires a confirmed row, so
     * applying, messaging and signing wait on it.
     */
    case SchoolEmail = 'school_email';

    /**
     * Get the display label for the provider.
     */
    public function label(): string
    {
        return match ($this) {
            self::Document => 'Uploaded document',
            self::SchoolEmail => 'School email',
        };
    }
}
