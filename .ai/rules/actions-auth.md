---
paths:
  - 'app/Actions/Auth/**'
---

# Actions Auth

## Microsoft identity is the userPrincipalName; a bound Google id always wins
ResolveMicrosoftUser uses getEmail(), which socialiteproviders/microsoft maps to userPrincipalName — Entra only issues UPNs on tenant-verified domains. Never switch to the Graph `mail` attribute: a tenant admin can type any address there (the "nOAuth" takeover). Tenant defaults to `organizations`; isConsumerTenant() is still checked in case someone sets `common`.
Students sign in with a school address and bind a personal Google account in settings for after graduation. So ResolveGoogleUser::findExisting asks google_id BEFORE email, and link() refuses (never overwrites) an account that already has a different google_id. ResolveMicrosoftUser logs each refusal by reason code only — keep addresses out of logs.
