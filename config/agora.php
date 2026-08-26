<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Agora RTC — video meetings
    |--------------------------------------------------------------------------
    |
    | The video half of the collaboration tools. Audio and video only: the
    | live "a message just landed" ping is Laravel Reverb's job and stays
    | there, so this file has nothing to do with chat delivery.
    |
    | Meeting rows live in this database. Agora holds no state that matters —
    | a channel exists because somebody joined it, and stops existing when the
    | last person leaves. Everything worth keeping about a meeting is in the
    | `meetings` table.
    |
    | While `enabled` is false — the shipped default — no token is issued and
    | the call routes 404, so the module is absent rather than broken.
    |
    */

    'enabled' => (bool) env('AGORA_ENABLED', false),

    /*
    | The App ID identifies the Agora project and is NOT a secret: the browser
    | needs it to open a connection, so it ships in the client bundle. That is
    | why there is a VITE_ copy of it and no VITE_ copy of the certificate.
    */
    'app_id' => env('AGORA_APP_ID'),

    /*
    | The App Certificate signs the tokens and IS a secret. It never reaches
    | the browser and never appears in a route response.
    |
    | Every token this app mints is scoped to one channel and one user, and is
    | only issued after the same participation check App\Broadcasting\
    | ConversationChannel makes. Agora has no idea who may read a thread; with
    | Reverb that gate was Laravel's broadcast auth endpoint, and moving off
    | Reverb moves the gate here. A token minted without that check hands the
    | holder someone else's conversation.
    */
    'app_certificate' => env('AGORA_APP_CERTIFICATE'),

    /*
    | How long a minted token stays valid, in seconds. Short enough that a
    | leaked one expires on its own, long enough to outlast a sitting.
    */
    'token_ttl' => (int) env('AGORA_TOKEN_TTL', 3600),

    /*
    | Seconds to wait on Agora's REST API. Short on purpose, for the same
    | reason config/sheerid.php is: nobody waits on a courtesy.
    */
    'timeout' => (int) env('AGORA_TIMEOUT', 5),

];
