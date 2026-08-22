<?php

return [

    /*
    |--------------------------------------------------------------------------
    | One-Time Password
    |--------------------------------------------------------------------------
    |
    | The emailed code that proves somebody can actually open the address they
    | typed. Used when registering, and again when a deactivated account asks
    | to appeal — the second case cannot ask for a password, because accounts
    | created through Google do not have one.
    |
    */

    'length' => 6,

    /* How long a code stays usable, in minutes. */
    'expires_after' => 10,

    /*
     * How many wrong guesses a code survives. Low on purpose: six digits is
     * only a million combinations, and the resend floor below is what stops
     * somebody simply asking for a fresh one after every fifth try.
     */
    'max_attempts' => 5,

    /* The shortest gap between two codes for the same address, in seconds. */
    'resend_after' => 60,

];
