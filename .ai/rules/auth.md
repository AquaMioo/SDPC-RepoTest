---
paths:
  - app/Http/Controllers/Auth/RegistrationController.php
  - app/Http/Controllers/Auth/AccountAppealController.php
  - app/Http/Middleware/EnsureAccountIsNotMonitored.php
---

# Auth

## Registration is ours, not Fortify's — the account exists only after the code
Features::registration() is removed from config/fortify.php on purpose. Fortify creates the account and signs the person in on one request, and there is no seam in that where an email address can be proved first. RegistrationController owns the `register` and `register.store` names instead, so every existing route() call and Wayfinder helper keeps resolving.

POST /register validates, stashes the payload in App\Support\PendingRegistration (session), and mails a code. Nothing is written until POST /register/verify comes back correct — no User row, no team, no claim on the address in the unique index. A pending Google identity skips the code entirely; Google already proved the address.

CreateNewUser therefore sets email_verified_at unconditionally: nothing reaches it with an unproved address. It still validates its own input — it is the Fortify contract and may be called with an array nobody checked. password_confirmation is carried in the stashed payload on purpose, because validated() drops it and CreateNewUser's `confirmed` rule would fail without it.

The admin portal is untouched. Never add an OTP step to routes/admin.php.

## Monitoring is a hold, not a ban — never gate the appeal behind it
UserStatus::Monitored still returns true from canAuthenticate(): the account must be able to sign in to write its appeal. What it loses is acting — EnsureAccountIsNotMonitored rides beside EnsureAccountIsVerified on exactly the same routes in routes/client.php and routes/student.php (both hold the pair in a local `$verified` array), plus the agreement routes that accept new terms.

Deliberately absent from: settings (profile.appeal.store lives in the auth-only group), messaging, reports, and agreements.milestones.update — freezing milestones would punish the other signatory for a decision that is not theirs.

Deactivated accounts never reach that middleware; they cannot sign in at all, so their appeal is the guest page at /appeal. It proves identity with an emailed code rather than a password, because an account created through Google never had one. Nothing on that page reveals whether an address has an account: the code step appears either way, and a code is only actually sent when there is something to appeal.

## One OTP mechanism, two purposes
App\Services\Verification\OneTimePasswordService backs both registration and appeals. The purpose is part of the unique key, so a registration code cannot be replayed as an appeal code. Codes are hashed at rest, expire, carry an attempt budget, and have a resend floor — see config/otp.php. A correct code is consumed on the way out, so pressing back does not open the door twice.
