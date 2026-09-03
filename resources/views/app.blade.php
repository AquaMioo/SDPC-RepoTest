<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-mod="{{ request()->is('admin*') ? 'admin' : 'user' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- There is deliberately no appearance preference. data-mod above is the
             only palette switch: the user app is light, the admin portal is dark,
             and neither follows the visitor's OS. --}}

        {{-- Nocturne paints the whole viewport. The ground is set here so overscroll
             and the pre-hydration frame match the design instead of flashing white.
             Layouts re-point data-mod on the root element to swap palette. --}}
        <style>
            html {
                background-color: #e3e3e3;
            }

            html[data-mod='admin'] {
                background-color: #0c1614;
            }

            /* The frame before React answers.

               Written here, in literal colours, on purpose: this has to paint
               before app.css has been fetched, so it can use neither the
               Nocturne tokens nor a utility class. The values are the same
               ones the [data-mod] palettes resolve to.

               It hides itself the moment Inertia mounts. #app is genuinely
               empty until React puts something in it, so :not(:empty) is the
               signal — no script, nothing to fire late, nothing left behind
               if the bundle never arrives. */
            #boot {
                position: fixed;
                inset: 0;
                z-index: 9999;
                padding: 0;
                background-color: #e3e3e3;
                pointer-events: none;
            }

            html[data-mod='admin'] #boot { background-color: #0c1614; }

            #app:not(:empty) + #boot { display: none; }

            #boot .boot-bar {
                height: 61px;
                border-bottom: 1px solid rgba(28, 31, 25, 0.09);
            }

            html[data-mod='admin'] #boot .boot-bar {
                border-bottom-color: rgba(230, 239, 234, 0.12);
            }

            #boot .boot-shell {
                max-width: 1320px;
                margin: 0 auto;
                padding: 30px clamp(16px, 4vw, 32px);
                display: flex;
                flex-direction: column;
                gap: 22px;
            }

            #boot .boot-row {
                display: flex;
                gap: 22px;
                flex-wrap: wrap;
            }

            #boot i {
                display: block;
                border-radius: 8px;
                background-color: rgba(28, 31, 25, 0.07);
                animation: boot-pulse 1.4s ease-in-out infinite;
            }

            html[data-mod='admin'] #boot i {
                background-color: rgba(230, 239, 234, 0.08);
            }

            @keyframes boot-pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.45; }
            }

            /* A pulse is decoration; somebody who asked for less motion still
               needs to see the shapes. */
            @media (prefers-reduced-motion: reduce) {
                #boot i { animation: none; }
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />

        {{-- Replaced by the app the instant it mounts; see #boot in the head. --}}
        <div id="boot" aria-hidden="true">
            <div class="boot-bar"></div>
            <div class="boot-shell">
                <i style="width: 220px; height: 26px"></i>
                <i style="width: 320px; height: 14px"></i>
                <div class="boot-row">
                    <i style="flex: 1 1 260px; height: 240px"></i>
                    <i style="flex: 1 1 260px; height: 240px"></i>
                    <i style="flex: 1 1 260px; height: 240px"></i>
                </div>
                <i style="width: 100%; height: 96px"></i>
            </div>
        </div>
    </body>
</html>
