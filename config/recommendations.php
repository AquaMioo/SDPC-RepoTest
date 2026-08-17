<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Matching Driver
    |--------------------------------------------------------------------------
    |
    | "computed" scores on the spot from skills, the words in the brief, and
    | the preferences a client set on the posting. It needs no model, no key
    | and no network, and it answers a free-text search — which a precomputed
    | table cannot, since the client has not written the brief yet.
    |
    | "stored" reads whatever the recommendations table already holds, for when
    | something else is generating scores on a schedule.
    |
    */

    'driver' => env('RECOMMENDATIONS_DRIVER', 'computed'),

];
