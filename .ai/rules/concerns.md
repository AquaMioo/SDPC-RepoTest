---
paths:
  - app/Concerns/RegistrationValidationRules.php
---

# Concerns

## A student's account email IS the school address; .edu.ph is a format check only
Student sign up has no `email` field: `school_email` (SchoolEmailAddress rule, unique on users.email) becomes users.email and receives the OTP. A pending Google identity may only register a client; a pending Microsoft identity only a student (role Rule::in). SchoolEmailAddress admits any *.edu.ph label-by-label and must never grant verification — the gate stays on exact School::forEmailDomain() (see verification.md). CreateNewUser records a SchoolEmail verification at sign up only for an exactly-listed domain. ProfileUpdateRequest applies the same rule to a student only when the address CHANGES, so pre-change students with personal emails can still save.
