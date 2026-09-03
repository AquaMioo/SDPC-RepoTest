<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        /*
         * Linked per directory, not wholesale.
         *
         * public_path('storage') => storage_path('app/public') published
         * EVERYTHING on the disk, and message attachments live on it — so a
         * picture sent inside a private conversation was fetchable by anyone
         * holding the URL, signed out, with no participant check anywhere in
         * the path. Confirmed by fetching one with no cookies: 200, 132KB.
         *
         * The files cannot simply move: the Railway volume is mounted at
         * storage/app/public, and anything written outside it is on the
         * container's own disk and disappears on the next deploy. So the
         * publishing narrows instead. Avatars and business logos are meant to
         * be public — a logo is drawn on the landing page — and message
         * images are served by a route that checks the thread.
         *
         * Adding a directory here publishes it. Do not add message-images.
         */
        public_path('storage/avatars') => storage_path('app/public/avatars'),
        public_path('storage/business-logos') => storage_path('app/public/business-logos'),
    ],

];
