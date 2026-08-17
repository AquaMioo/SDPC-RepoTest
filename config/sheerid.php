<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SheerID Student Verification
    |--------------------------------------------------------------------------
    |
    | An optional third-party check on whether a student is really enrolled.
    |
    | OPTIONAL IS THE WHOLE POINT. Nothing on this platform gates on the
    | answer: applying, messaging and signing still wait on the credential
    | document an administrator reviews by hand. A SheerID pass adds a badge
    | and gives that administrator one more piece of evidence, and that is the
    | entire extent of its power. Do not wire a permission check to it.
    |
    | While `enabled` is false — the shipped default, because the project has
    | no credentials yet — AppServiceProvider binds NullStudentVerifier, the
    | settings button is hidden and the routes 404. Setting the three values
    | below is all that switching it on takes.
    |
    */

    'enabled' => (bool) env('SHEERID_ENABLED', false),

    'base_url' => env('SHEERID_BASE_URL', 'https://services.sheerid.com/rest/v2'),

    /*
    | Where the student finishes the check. SheerID hosts the form itself, so
    | the platform never handles the enrolment documents or sees the answer
    | until it asks for it.
    */
    'hosted_url' => env('SHEERID_HOSTED_URL', 'https://services.sheerid.com/verify'),

    'program_id' => env('SHEERID_PROGRAM_ID'),

    'access_token' => env('SHEERID_ACCESS_TOKEN'),

    /*
    | Seconds to wait on the provider. Short on purpose: a student pressing a
    | button that is not required must never be left watching a spinner.
    */
    'timeout' => (int) env('SHEERID_TIMEOUT', 10),

];
