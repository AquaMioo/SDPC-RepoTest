<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | The addresses allowed to tell the app, through X-Forwarded-* headers,
    | who the visitor really is and whether they arrived over HTTPS. Read by
    | Laravel's TrustProxies middleware; a comma-separated list of IPs or
    | CIDR ranges, or "*" for whatever is directly in front.
    |
    | Behind something that terminates TLS the app is spoken to over plain
    | HTTP. Without trusting that hop, route() and asset() emit http:// links
    | an https:// page refuses to load, the session cookie loses its Secure
    | flag, and AddSecurityHeaders never sends HSTS.
    |
    | "*" (the default) is right on Railway, where the platform's edge is the
    | only way in. It is WRONG anywhere the app can be reached directly: any
    | client could then forge X-Forwarded-For, pose as another address and
    | walk around the per-IP login throttles.
    |
    | The self-hosted server sets TRUSTED_PROXIES=127.0.0.1 — only
    | cloudflared, on the same PC, forwards visitors to FrankenPHP.
    |
    */

    'proxies' => env('TRUSTED_PROXIES', '*'),

];
