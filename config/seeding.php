<?php

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
    | Leave them unset and the seeder falls back to "password", which is fine
    | for a throwaway local database and obviously unsafe anywhere else.
    |
    */

    'admin_password' => env('SEED_ADMIN_PASSWORD', 'password'),

    'tester_password' => env('SEED_TESTER_PASSWORD', 'password'),

];
