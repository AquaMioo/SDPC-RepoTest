<?php

namespace App\Enums;

/**
 * What an emailed code is being used to prove.
 *
 * The purpose is part of the key, so a code issued to finish signing up cannot
 * be replayed to open somebody's appeal, and asking for one does not quietly
 * invalidate the other.
 */
enum OneTimePasswordPurpose: string
{
    case Registration = 'registration';
    case Appeal = 'appeal';

    /**
     * Proving control of a school-issued address.
     *
     * A separate case rather than reusing Registration: the purpose is part of
     * the key, so a code mailed to finish signing up can never be replayed to
     * claim a student is enrolled. See .ai/rules/auth.md.
     */
    case SchoolEmail = 'school_email';

    /**
     * Signing a student in without a password.
     *
     * Students sign up with their school address and no password (2026-09-20),
     * so a code mailed to that address is how they get back in — unless they
     * have bound a Google account, which skips it. Its own case so a sign in
     * code can never finish a sign up, or the other way round.
     */
    case Login = 'login';
}
