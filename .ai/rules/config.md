---
paths:
  - config/billing.php
  - config/trustedproxy.php
  - config/database.php
  - config/broadcasting.php
---

# Config

## Billing is built and dormant; RecordTransaction is the single door
The transactions table, model, controller, screen and tests all exist and all ship switched off. config('billing.enabled') defaults to false: the routes 404 via EnsureBillingIsEnabled, the nav item stays disabled off the shared billingEnabled prop, and App\Actions\Billing\RecordTransaction returns null without writing.

Everything that would write a ledger row goes through RecordTransaction. That is what makes the flag a one-line switch instead of a hunt — callers such as AgreementMilestoneController call it unconditionally and never ask whether billing is live.

Tests that need the screen force config(['billing.enabled' => true]). The shipped default stays false until the payment arrangements are actually settled. Do not add a payment gateway package to turn this on.

## SheerID was removed — the school-email check is the only verifier
SheerID (config/sheerid.php, SheerIdStudentVerifier, its routes and settings card, and the student_verifications external_id/redirect_url columns) was taken out on 2026-09-17; it was never switched on. AppServiceProvider binds SchoolEmailVerifier when it is available, NullStudentVerifier otherwise. Do not bring a third-party verifier back without restoring the availability-gate thinking in .ai/rules/verification.md first.

## Trusted proxies come from TRUSTED_PROXIES, never a hard-coded at: in bootstrap/app.php
TrustProxies reads config('trustedproxy.proxies') only when no `at:` was given, so bootstrap/app.php must not call trustProxies(at: ...) — that static override would beat the config on every host. Default '*' is right on Railway (its edge is the only way in). The self-hosted Windows server behind Cloudflare Tunnel sets TRUSTED_PROXIES=127.0.0.1: anything reachable directly must not trust '*', or clients forge X-Forwarded-For past the per-IP login throttles. tests/Feature/Http/TrustedProxiesTest.php pins both.

## MySQL sessions are pinned to UTC; never leave DB_TIMEZONE to the server's clock
config('app.timezone') is UTC, and TIMESTAMP columns are converted through the MySQL *session* time zone. The mysql and mariadb connections therefore set 'timezone' => env('DB_TIMEZONE', '+00:00'). Without it the connection inherits the server clock: after moving Railway's UTC data to the self-hosted MySQL (Philippine time, 2026-09-19) every imported TIMESTAMP read back 8 hours in the future, and the moved last_seen_at stamps locked accounts as "already being used on another device" with nobody signed in. Do not remove the setting or point DB_TIMEZONE at local time. When moving data between servers, use mysqldump's default --tz-utc and keep both apps on +00:00. tests/Feature/DatabaseTimezoneTest.php pins it.

## Server-side broadcasts must reach Reverb on localhost, never through Cloudflare
REVERB_HOST/PORT/SCHEME are what Laravel itself uses to POST events to Reverb; VITE_REVERB_* are what the browser bundle is built with. On a self-hosted box they must differ: REVERB_HOST=127.0.0.1 with the local port and http, and VITE_REVERB_HOST=<public ws hostname>, 443, https, written out in full. Do not use "${REVERB_HOST}" interpolation there, or the next build ships 127.0.0.1 to browsers.

Routing server broadcasts out through the public hostname and back in through the tunnel breaks the moment Cloudflare Bot Fight Mode is on. The VM's Guzzle request gets the "Just a moment..." challenge (403, cf-mitigated: challenge), BroadcastException is swallowed by design, and messages save but never appear live. Bot Fight Mode cannot be bypassed with rules on the free plan. This hit demo.sdpc.tech on 2026-09-21; sdpc.tech (PC) was already on 127.0.0.1:8081.
