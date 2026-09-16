---
paths:
  - app/Http/Requests/Client/UpdateClientProfileRequest.php
---

# Requests Client

## Business phone numbers are Philippine only, stored as +63 and the national number
This replaces the earlier "digits only" rule. Testers asked for +63 only. The contacts dialog shows a fixed +63 and holds only what follows it (afterPlus63 in profile-dialogs.tsx). prepareForValidation() turns 0917…, 63917… and "+63 917 …" into +639171234567, and the rule is PHILIPPINE_NUMBER: a 10-digit mobile starting with 9, or a 9-digit landline with its area code. Website and Facebook fields get https:// added when it's missing. Both normalisations run only for keys that were sent, because the company details dialog PATCHes the same route without them and a merged null would wipe them. No URL-looking placeholders on these fields: "https://example.test" in grey read as a website already saved. Migration 2026_09_16_145155 rewrote stored numbers.
