<?php

/*
| env() hands back "" rather than the default for a key that is present but
| empty, and .env.example ships both of these keys present and blank. Reading
| them into variables first is what lets the blank case fall back below.
*/
$adminPassword = env('SEED_ADMIN_PASSWORD');
$testerPassword = env('SEED_TESTER_PASSWORD');

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded Account Passwords
    |--------------------------------------------------------------------------
    |
    | The passwords DatabaseSeeder gives the accounts it creates. They are read
    | from the environment rather than written into the seeder, because this
    | repository is public and a known administrator password baked into the
    | source is a real hazard the moment the app is deployed anywhere.
    |
    | Leave them unset -- or blank, which is how a fresh clone arrives after
    | copying .env.example -- and the seeder falls back to "password", which is
    | fine for a throwaway local database and obviously unsafe anywhere else.
    | Without the blank check the seeder hands every account the empty string
    | as its password, and a clone that looks correctly seeded rejects every
    | documented login with "these credentials do not match our records".
    |
    */

    'admin_password' => filled($adminPassword) ? $adminPassword : 'password',

    'tester_password' => filled($testerPassword) ? $testerPassword : 'password',

];
