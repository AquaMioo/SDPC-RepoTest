<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Agora Signaling (RTM)
    |--------------------------------------------------------------------------
    |
    | The live half of messaging: the "a message just landed" ping that tells
    | an open thread to refresh itself.
    |
    | Agora carries the ping and nothing else. Conversations and messages stay
    | in this database, in the tables they are already in — Signaling is not a
    | message store here and must not become one. If a ping is lost the thread
    | still catches up, because resources/js/pages/messaging/index.tsx polls
    | every 30 seconds regardless.
    |
    | That is the same bargain Reverb was held to. App\Actions\Messaging\
    | AnnounceMessage catches everything a broadcaster throws and logs it, so a
    | signalling outage can never fail a message that has already been written.
    | Whatever replaces Reverb inherits that rule rather than renegotiating it.
    |
    | While `enabled` is false — the shipped default, and the right default
    | until a token has actually been minted and a ping actually received —
    | nothing below is read and messaging behaves exactly as it does today.
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
