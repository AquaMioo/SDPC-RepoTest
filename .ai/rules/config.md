---
paths:
  - config/billing.php
---

# Config

## Billing is built and dormant; RecordTransaction is the single door
The transactions table, model, controller, screen and tests all exist and all ship switched off. config('billing.enabled') defaults to false: the routes 404 via EnsureBillingIsEnabled, the nav item stays disabled off the shared billingEnabled prop, and App\Actions\Billing\RecordTransaction returns null without writing.

Everything that would write a ledger row goes through RecordTransaction. That is what makes the flag a one-line switch instead of a hunt — callers such as AgreementMilestoneController call it unconditionally and never ask whether billing is live.

Tests that need the screen force config(['billing.enabled' => true]). The shipped default stays false until the payment arrangements are actually settled. Do not add a payment gateway package to turn this on.

## SheerID was removed — the school-email check is the only verifier
SheerID (config/sheerid.php, SheerIdStudentVerifier, its routes and settings card, and the student_verifications external_id/redirect_url columns) was taken out on 2026-09-17; it was never switched on. AppServiceProvider binds SchoolEmailVerifier when it is available, NullStudentVerifier otherwise. Do not bring a third-party verifier back without restoring the availability-gate thinking in .ai/rules/verification.md first.
