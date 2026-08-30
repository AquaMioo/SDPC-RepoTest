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
}
