---
paths:
  - app/Http/Controllers/Auth/RegistrationController.php
  - app/Http/Controllers/Auth/AccountAppealController.php
  - app/Http/Middleware/EnsureAccountIsNotMonitored.php
  - 'app/Http/Middleware/**'
---

# Auth

## Registration is ours, not Fortify's — the account exists only after the code
Features::registration() is removed from config/fortify.php on purpose. Fortify creates the account and signs the person in on one request, and there is no seam in that where an email address can be proved first. RegistrationController owns the `register` and `register.store` names instead, so every existing route() call and Wayfinder helper keeps resolving.

POST /register validates, stashes the payload in App\Support\PendingRegistration (session), and mails a code. Nothing is written until POST /register/verify comes back correct — no User row, no team, no claim on the address in the unique index. A pending Google identity skips the code entirely; Google already proved the address.

CreateNewUser therefore sets email_verified_at unconditionally: nothing reaches it with an unproved address. It still validates its own input — it is the Fortify contract and may be called with an array nobody checked. password_confirmation is carried in the stashed payload on purpose, because validated() drops it and CreateNewUser's `confirmed` rule would fail without it.

The admin portal is untouched. Never add an OTP step to routes/admin.php.

## Monitoring is a hold, not a ban — never gate the appeal behind it
A monitored account signs in and keeps the whole platform for reading: it must be able to write its appeal. What it loses is acting — EnsureAccountIsNotMonitored rides beside EnsureAccountIsVerified on exactly the same routes in routes/client.php and routes/student.php (both hold the pair in a local `$verified` array), plus the agreement routes that accept new terms.

Deliberately absent from: settings (profile.appeal.store lives in the auth-only group), messaging, reports, and agreements.milestones.update — freezing milestones would punish the other signatory for a decision that is not theirs.

Deactivated accounts sign in too (since 2026-09-18), but ConfineDeactivatedAccounts holds them inside Settings: they write their appeal there. The guest page at /appeal stays for anyone who cannot sign in (a forgotten password, a closed school mailbox). It proves identity with an emailed code rather than a password, because an account created through Google never had one. Nothing on that page reveals whether an address has an account: the code step appears either way, and a code is only actually sent when there is something to appeal.

## One OTP mechanism, two purposes
App\Services\Verification\OneTimePasswordService backs both registration and appeals. The purpose is part of the unique key, so a registration code cannot be replayed as an appeal code. Codes are hashed at rest, expire, carry an attempt budget, and have a resend floor — see config/otp.php. A correct code is consumed on the way out, so pressing back does not open the door twice.

## Every observable on the guest appeal page must come from the session, not the code row
A code is only really sent when there is something to appeal, so anything derived from the OneTimePassword row differs between a held account and any other address — and that difference is the answer to "has this address been deactivated?".

Three leaked before they were closed: the rejected-code message (Missing vs Mismatch), the resend toast (decided on whether send() succeeded), and secondsUntilResend (read off the row, so only held accounts got a countdown on the button).

So: the resend clock lives in the session under `appeal.code_sent_at`, written whether or not an email went out; the resend toast is decided by that clock alone; and every rejected code gets the single CODE_REJECTED message rather than OneTimePasswordResult::message(). Do not "improve" that message back into the specific one — the specificity is safe on registration, where a code is always sent, and is an account oracle here. Three tests in tests/Feature/Admin/AppealTest.php pin all three.

## Back out of a portal and the session ends — all three parts are required
Pressing Back on a dashboard and then Forward used to redraw it with nobody re-authenticated. Fixing it takes three pieces, and any one alone does nothing:

1. AddSecurityHeaders sends no-store on guest-only routes as well as signed-in ones, read off the route's own middleware rather than a name list, so every portal is covered.
2. Sign-in rotates Inertia's history key — see app/Http/Responses. Without this the browser never issues the request, and items 1 and 3 never run.
3. EndSessionOnLoginScreen treats arriving at a login screen while signed in as the end of the visit: logout, invalidate, regenerate token, clearHistory.

GET only — a POST that ended the session before Fortify read the credentials would make logging in impossible. Screens are matched by exact route name, not by a *.login pattern, because the two-factor challenge is also a *.login route and must stay reachable mid-sign-in.

Known trade-off: this fires on any arrival at a login screen while signed in, not only on Back. A bookmark or a mailed "Log in" link ends the session too.

## One device per account: EnforceSingleSession must stay first and refuse with logoutCurrentDevice()
App\Support\AccountSession + EnforceSingleSession let one session hold an account (token in session + users.active_session_token); "in use" = User::isOnline(). It sits FIRST in the web group so TouchLastSeen and EndSessionOnLoginScreen only ever see the holder — a refused device that stamped last_seen_at, or reached EndSessionOnLoginScreen's logout(), would free the lock for itself.

Refusals use logoutCurrentDevice(), never logout(): logout() fires Logout → ClearLastSeen nulls the stamp (frees the account for the intruder) and cycles the holder's remember token. It checks before $next (signed-in requests) AND after (the request that signs in — password, 2FA, Google, registration), so no sign-in path bypasses it. Password reset (PasswordReset → ReleaseAccountSession) is the owner's way back in. Session token is keyed by user id so actingAs switching users in tests still works.

A closed tab used to hold the account for the whole presence window (5 minutes), so the owner's own second browser was told "already being used on another device". AccountSessionGuard now keeps a per-account open-tab list in localStorage and, when the LAST tab's `pagehide` fires, sends a keepalive POST to `session.leave`. AccountSession::leave() nulls last_seen_at only — it keeps the token, so the same browser coming back is still the holder and its next request re-stamps presence — and only for the session that holds the account (a replaced session gets 409 from EnforceSingleSession first, and leave() checks again). If the request never arrives, the presence window is still the fallback.

The same signal enforces "Keep me logged in" (agreed with the team 2026-09-19): while an account is in use nobody else gets in; once its last tab closes, a browser WITHOUT a valid recaller cookie is signed out on its next request (AccountSession::CLOSED), and one WITH it stays signed in. leave() reads the recaller cookie itself (Illuminate\Auth\Recaller, checked against the user's remember_token) rather than trusting the page. A refresh fires the same pagehide, so the mark only signs out after LEAVE_GRACE_SECONDS (30 s): the reloaded page reports in 3 s after load (a forced heartbeat), which uses the mark up. Clicks on plain links and non-Inertia form submits (full-page loads the person started, e.g. linking Google) do not send leave at all — Inertia visits are told apart by defaultPrevented. Google and Microsoft sign-in always log in with remember: true. Private windows need nothing extra: closing one deletes its cookies, so it is signed out either way.

## A deactivated account is confined to Settings by an allow-list, not by gating each module
ConfineDeactivatedAccounts runs in the web group right after EndSessionOnLoginScreen and sends a deactivated account back to profile.edit (JSON requests get 403) unless the route is in OPEN_ROUTES: home, legal, login screens, logout, session.heartbeat, session.leave, profile.edit/appeal.store/destroy, the Security routes (security.edit, user-password.update, password.confirm*, two-factor.*, passkey.*), student.google.* and verification.*. New modules are closed by default — only widen OPEN_ROUTES for something that is genuinely part of Settings. broadcasting/auth is open only for the account's own `private-App.Models.User.{id}` channel (the sign-in alert), never for a conversation. AuthHome sends a deactivated account straight to settings; ClientLayout greys out every nav item and icon except Settings. tests/Feature/Auth/DeactivatedAccountTest.php pins it.
