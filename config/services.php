<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),

        // Drives whether the "Continue with Google" button is rendered and
        // whether the OAuth routes respond at all.
        'enabled' => filled(env('GOOGLE_CLIENT_ID')) && filled(env('GOOGLE_CLIENT_SECRET')),
    ],

    /*
     * Students' school Microsoft 365 accounts, through the
     * socialiteproviders/microsoft driver (registered in AppServiceProvider).
     */
    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI', '/auth/microsoft/callback'),

        // "organizations" admits school and work accounts from any tenant and
        // turns personal Outlook/Hotmail accounts away at Microsoft's end.
        // A tenant id here would admit that one school only.
        'tenant' => env('MICROSOFT_TENANT_ID', 'organizations'),

        // Drives whether the "Continue with Microsoft" button is rendered and
        // whether the OAuth routes respond at all.
        'enabled' => filled(env('MICROSOFT_CLIENT_ID')) && filled(env('MICROSOFT_CLIENT_SECRET')),
    ],

    /*
     * Brevo, over its HTTP API rather than SMTP — the deploy host blocks
     * outbound 587/465/2525, so SMTP cannot leave the container at all.
     * See App\Mail\Transport\BrevoTransport.
     */
    'brevo' => [
        'key' => env('BREVO_API_KEY'),
        'endpoint' => env('BREVO_ENDPOINT', 'https://api.brevo.com/v3/smtp/email'),
        'timeout' => (int) env('BREVO_TIMEOUT', 10),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
