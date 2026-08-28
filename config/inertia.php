<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configures if and how Inertia uses Server Side Rendering
    | to pre-render each initial request made to your application's pages
    | so that server rendered HTML is delivered for the user's browser.
    |
    | See: https://inertiajs.com/server-side-rendering
    |
    */

    /*
     * Server-side rendering.
     *
     * On by default because the Vite dev server hosts the SSR endpoint itself
     * — nothing extra to run locally. In production it is a separate process
     * (`php artisan inertia:start-ssr`) listening on the url below, and if it
     * is not running Laravel attempts the render, fails, and falls back to the
     * browser on every single request. The pages still work; they just each
     * pay for a failed HTTP call first.
     *
     * So on a host where you are not running that fourth process, set
     * INERTIA_SSR_ENABLED=false rather than leaving this pointed at a port
     * with nothing behind it.
     */
    'ssr' => [
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', true),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
        // 'bundle' => base_path('bootstrap/ssr/ssr.mjs'),

    ],

    /*
    |--------------------------------------------------------------------------
    | History Encryption
    |--------------------------------------------------------------------------
    |
    | Inertia keeps each visited page's props in the browser's history state so
    | that going back is instant. Without this that state outlives the session:
    | after logging out you could press Back — or Forward, from the login
    | screen you had just been returned to — and the admin dashboard rendered
    | again, users, counts and review queue included, straight out of history.
    |
    | Encrypted, that state is only readable with a key held in session
    | storage. ClearInertiaHistory listens for the logout event and rotates the
    | key, so every earlier entry becomes undecryptable and Inertia asks the
    | server for the page instead — which redirects to the login screen.
    |
    | Needs window.crypto.subtle, so it is inert over plain http on anything
    | that is not localhost. Production is HTTPS.
    |
    */

    'history' => [

        'encrypt' => true,

    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | These options configure how Inertia discovers page components on the
    | filesystem. The paths and extensions are used to locate components
    | when rendering responses and during testing assertions.
    |
    */

    'pages' => [

        'paths' => [
            resource_path('js/pages'),
        ],

        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The values described here are used to locate Inertia components on the
    | filesystem. For instance, when using `assertInertia`, the assertion
    | attempts to locate the component as a file relative to the paths.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

    ],

];
