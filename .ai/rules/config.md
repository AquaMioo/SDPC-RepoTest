---
paths:
  - config/billing.php
  - config/sheerid.php
---

# Config

## Billing is built and dormant; RecordTransaction is the single door
The transactions table, model, controller, screen and tests all exist and all ship switched off. config('billing.enabled') defaults to false: the routes 404 via EnsureBillingIsEnabled, the nav item stays disabled off the shared billingEnabled prop, and App\Actions\Billing\RecordTransaction returns null without writing.

Everything that would write a ledger row goes through RecordTransaction. That is what makes the flag a one-line switch instead of a hunt — callers such as AgreementMilestoneController call it unconditionally and never ask whether billing is live.

Tests that need the screen force config(['billing.enabled' => true]). The shipped default stays false until the payment arrangements are actually settled. Do not add a payment gateway package to turn this on.

## SheerID is optional and nothing may gate on it
The third-party enrolment check is presentation only. User::isVerifiedStudent() decides whether a badge is drawn; User::isVerifiedForOperating() — the real gate behind applying, messaging and signing — still answers to the administrator-reviewed credential document alone. Never wire a permission, a middleware or a policy to a StudentVerification row.

NullStudentVerifier is the shipped binding, because the project has no credentials. AppServiceProvider swaps in SheerIdStudentVerifier only when config('sheerid.enabled') is true, and that class refuses to act unless program_id and access_token are both set. While it is off the settings button is hidden and both routes 404.

SheerIdStudentVerifier swallows every provider failure into a log line and an empty array. A verification service being down must never stop a student using the platform, because the platform never needed it. Laravel's Http client only — no SDK package.
