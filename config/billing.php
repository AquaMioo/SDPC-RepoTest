<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing
    |--------------------------------------------------------------------------
    |
    | The money side of the platform — the Transaction screen, the milestone
    | ledger and the earnings figures — is built, migrated and tested, and it
    | ships switched off.
    |
    | While this is false the routes 404, the nav item stays disabled, and
    | App\Actions\Billing\RecordTransaction writes nothing. Nothing is missing:
    | the `transactions` table exists and the tests cover it with this flag
    | forced on, so turning it on is a configuration change rather than a build.
    |
    | Turn it on only once the payment arrangements the vision document
    | describes are actually settled. A ledger that shows figures nobody has
    | agreed to is worse than no ledger at all.
    |
    */

    'enabled' => (bool) env('BILLING_ENABLED', false),

];
