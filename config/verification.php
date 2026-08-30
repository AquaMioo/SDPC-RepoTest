<?php

return [

    /*
    |--------------------------------------------------------------------------
    | School Email Verification
    |--------------------------------------------------------------------------
    |
    | Proving a student is a student by mailing a code to an address their
    | school issued, and having them read it back.
    |
    | WHAT THIS PROVES, EXACTLY: that somebody can open mail at an address on a
    | domain an administrator put on the list. It does NOT prove current
    | enrolment. Alumni keep their addresses for years, staff share the domain,
    | and a school that never deprovisions anybody will let a 2019 graduate
    | through. Say that plainly rather than claiming more than it does — the
    | uploaded document an administrator reads is still the stronger evidence,
    | and it stays.
    |
    | OFF BY DEFAULT, AND THAT IS LOAD-BEARING. While this is false the shipped
    | NullStudentVerifier reports itself unavailable and
    | User::hasPassedStudentVerification() returns true for everybody — which
    | is exactly how the platform behaves today. Turning it on makes every
    | student who has not verified fail the gate at once: no applying, no
    | messaging, no signing. Seed or backfill before you flip it, or you will
    | lock out the accounts you are about to demo.
    |
    */

    'school_email' => [

        'enabled' => (bool) env('SCHOOL_EMAIL_VERIFICATION_ENABLED', false),

        /*
        | Domains live on the schools table, not here, so an administrator can
        | add one without a deploy. A school row with a null domain simply
        | cannot be used for this route.
        |
        | The verifier reports itself unavailable when no school has a domain
        | yet, so switching this on before any are configured does nothing
        | rather than locking everybody out.
        */

    ],

];
