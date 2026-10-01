---
paths:
  - app/Concerns/RegistrationValidationRules.php
---

# Concerns

## A student's account email IS the school address; .edu.ph is a format check only
Student sign up has no `email` field: `school_email` (SchoolEmailAddress rule, unique on users.email) becomes users.email and receives the OTP. A pending Google identity may only register a client; a pending Microsoft identity only a student (role Rule::in). SchoolEmailAddress admits any *.edu.ph label-by-label and must never grant verification — the gate stays on exact School::forEmailDomain() (see verification.md). CreateNewUser records a SchoolEmail verification at sign up only for an exactly-listed domain. ProfileUpdateRequest applies the same rule to a student only when the address CHANGES, so pre-change students with personal emails can still save.

## Disposable email addresses are refused on the server
App\Rules\NotDisposableEmail sits on the client `email` and student `school_email` in RegistrationValidationRules (so both RegisterRequest and CreateNewUser apply it) and on ProfileValidationRules::emailRules for email changes. It asks App\Support\DisposableEmailDomains, which reads resources/data/disposable-email-domains.txt — the CC0 community list from github.com/disposable-email-domains — plus an optional disposable-email-domains.local.txt for local additions. Domains are lowercased, and parent domains are checked, so "x.mailinator.com" is caught.

Refresh the list with `php artisan email:update-disposable-domains` and commit the file; it refuses a download under 1,000 domains. Nothing outside the list is refused, so no permanent provider is blocked for being unknown. The email-change rule skips the address the account already has, so an existing account on a listed domain can still save its profile — existing accounts are never removed for this. RegisterRequest trims the client email and lowercases its domain before validating.
