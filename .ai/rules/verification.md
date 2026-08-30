---
paths:
  - 'app/Services/Verification/**'
---

# Verification

## Verifier availability is the gate — turning one on locks out every student
User::hasPassedStudentVerification() returns TRUE for everybody while app(StudentVerifier::class)->isAvailable() is false. NullStudentVerifier is the shipped binding, so on a default install no student verification gates anything — applying, messaging and signing are open to every student. Do not read config/sheerid.php's claim that the "administrator-reviewed credential document" is the real gate; that rule is stale, the credential now only decides whether a badge is drawn.

So availability is a cliff, not a dial. The moment any verifier reports itself available, every student without a confirmed StudentVerification row loses applying, messaging and signing at once — including seeded demo accounts. Seed or backfill before flipping SCHOOL_EMAIL_VERIFICATION_ENABLED.

SchoolEmailVerifier guards this twice: the config flag, AND at least one school row carrying a domain. An install with the flag on but no domains configured reports unavailable rather than locking everyone out of a route nobody could complete.

Domain matching is EXACT equality, lowercased both sides, via School::forEmailDomain(). Never endsWith('.edu.ph') — that admits anyone who registers any .edu.ph domain — and never str_contains, which admits sti.edu.ph.attacker.com. Tests pin four lookalikes.
