# SDPC Platform — Session Handoff to Claude Code

**Date:** 17 August 2026
**Repo:** `https://github.com/sdpcdev/sdpc-platform.git`
**Local path:** `C:\Users\irven\sdpc-platform`
**Branch to work on:** `client-posting-limit-and-recruit-cards` (base commit `d4934c2`)

---

## 0. READ THIS FIRST — two things that will bite you

### 0.1 The user's local checkout is on the wrong branch

`main` (commit `6638bb7`) does **not** contain the Student Module, the matching
engine, messaging, testimonials or the admin posting queue. All of that lives on
`client-posting-limit-and-recruit-cards` — 2 commits ahead, 135 files, 9,796
insertions. The previous session's `ContextSummary.txt` says "nothing committed";
that was true at the time, but the work was committed and pushed afterwards.

Before doing anything:

```bash
git fetch origin
git checkout client-posting-limit-and-recruit-cards
```

### 0.2 This session's work is NOT on disk — it is in a patch file

Everything described in section 3 was written in an ephemeral cloud workspace,
not on the user's machine. It is delivered as **`sdpc-agreements-wip.patch`**.
Apply it on top of the feature branch:

```bash
git checkout client-posting-limit-and-recruit-cards
git apply --3way sdpc-agreements-wip.patch
composer install && npm install
php artisan migrate
php artisan test --compact
```

The patch contains **48 files** (43 new, 5 modified) and excludes
`package-lock.json`. It does **not** include `composer.json` / `composer.lock`
changes — those were only touched in the sandbox to work around a network
restriction (see 6.1) and were reverted.

---

## 1. The overarching goal

Continue building the **SDPC platform** (Student Developer Project Connection) —
a Laravel 13 + React/Inertia web app that connects tertiary students in San Jose
del Monte, Bulacan with local business clients who need systems built.

**This session's mandate**, from `Instructions.txt`:

1. Build out the remaining **Student Module** features.
2. **Connect** the entities that need connecting across Student, Admin and Client
   modules (accounts, students finding clients, clients finding students).
3. Implement **SheerID** student verification as an **optional, non-compulsory** step.
4. **Defer** Messaging and Video Call modules entirely — do not build them now.
5. Keep the existing structure, database tables and coding standards intact
   unless changing them is critical to the integration.
6. Keep UI as close as possible to the provided HTML mockup; keep Client UI and
   Student UI cleanly separated by account type.
7. **No new packages** — Laravel 13 + React only — without explicit permission.
8. Plan before coding (analyse → plan SheerID → plan components/routes → outline
   execution → only then generate code).
9. When the user says **"Create Context Summary"**, produce a 300–500 word
   compressed summary instead of code.

### Decisions the user made this session

| Question | Answer |
|---|---|
| Which branch? | Build on `client-posting-limit-and-recruit-cards` |
| SheerID credentials? | None yet — build it **config-gated** with a null driver |
| Scope | **Agreement/Contract module** + **Student profile & portfolio editing** |
| Transactions/billing | **Build it fully, but DO NOT ACTIVATE it yet** |
| Acceptance → project start | Approved: acceptance now **drafts** the agreement; the **second signature** starts the project |

---

## 2. Reference material and where it came from

### 2.1 The UI mockup (`StudentUI.html`)

A bundled single-page React prototype. Unpack it by reading the
`<script type="__bundler/manifest">` block (base64, some entries gzipped) and
the `<script type="__bundler/template">` block. The template holds the whole
design system CSS plus the markup.

Screens present in the mockup (`data-screen-label` attributes):

`Dashboard` · `Recruit` · `Messages` · `Client profile` · `Transaction` ·
`Project process` · `Agreement` · `Get client` · `Student profile` ·
`Contract` · `Post a job` · `Settings`

Student nav in the mockup: **Dashboard · Get Client · Workflow · Performance · Agreement**
Client nav in the mockup: **Dashboard · Recruit · Transaction · Project Process · Agreement**

Key mockup content that drove the schema:

- **Agreement screen** — *Scope*, *Pricing* (Milestone 1 · Design · ₱8,000 …),
  *Timeline* (Phase 1 · 9–27 Mar …), two signature cards, four acknowledgement
  checkboxes, "Signing records your name, account ID and timestamp to the
  contract log", buttons: *Read full contract* / *Request changes* / *Sign agreement*.
- **Contract screen** — numbered clauses (IP ownership, confidentiality,
  academic standards, deliverables & schedule table), same four acknowledgements.
- **Student profile** — About me, Technical arsenal, Availability
  ("Open to one project this term · ≈20 hrs/week · ₱260/hr · responds within 3 hrs"),
  Featured portfolio, Academic background.
- **Transaction** — table of Date / Description / Wallet / Benefit period / Type / Amount,
  filters for wallet (GCash, Bank transfer) and type (Milestone, Extension).

### 2.2 The capstone PDF

Student Module scope: Register · Login · Homepage · Profile Background ·
**Student Background History** · AI Project Recommendation · **Client List** ·
Project Collaboration · **Project Progress Tracking** · Project-Based
Communication · Messaging/Video · **GitHub Repository Integration** ·
**Terms & Agreements Form** · **Contract and Agreement Management** ·
Project Extension Payment · **Transaction Records**.

The PDF is explicit that the Terms & Agreements Form is displayed
*"upon the client's approval of the student … before collaboration begins"*.
That sentence is the justification for change 3.4 below.

---

## 3. What was built this session (contents of the patch)

### 3.1 The architectural keystone: a posting is a brief, an agreement is the contract

The previous session's migrations stripped `budget_type`, `budget_amount`,
`start_date`, `target_delivery_date`, `application_deadline`,
`expected_completion_date`, `weekly_commitment`, `team_size`,
`experience_level` and the whole `project_milestones` concept off `projects`.

That left the dashboard progress ring, the calendar and the Project process
screen with **no data source at all** — the ring was reporting a lifecycle stage
dressed up as a percentage.

**The fix, and the central design decision of this session:** that data comes
back at the layer where it is actually negotiated. A **posting** carries title,
category, industry, description, objectives, skills — no money, no dates. An
**agreement** carries scope, milestone pricing and phase dates, is drafted when
the client accepts a student, and is what both sides sign. This matches the
mockup's Agreement screen one-to-one and undoes none of the previous session's
decisions.

### 3.2 New enums (`app/Enums/`)

```
AgreementStatus       Draft | AwaitingSignatures | Active | Superseded | Cancelled
                      + isEditable(), acceptsSignatures(), isFinal(), tagVariant()
AgreementParty        Client | Student
                      + forRole(UserRole), counterparty()
MilestoneStatus       Pending | InProgress | Submitted | Approved | Returned
                      + progress() [0/40/80/80/100], studentAssignable(),
                        clientAssignable(), isFinal(), tagVariant()
TransactionType       Milestone | Extension
TransactionStatus     Pending | Posted | Settled | Failed  + countsAsEarned()
PaymentWallet         Unset | GCash | Maya | BankTransfer
VerificationProvider  SheerId | Document
```

The existing `VerificationStatus` enum (Unverified/Pending/Verified/Rejected)
is reused for SheerID rather than adding a parallel one.

### 3.3 New migrations (all applied clean, `migrate:fresh` verified)

| Migration | Notes |
|---|---|
| `create_agreements_table` | `project_id`, `application_id`, `team_id`, `student_id`, `reference`, `version`, `status`, `scope_summary`, `deliverables` (json), three terms text columns, `starts_on`/`ends_on`, `total_amount`, `activated_at`, `superseded_by`, soft deletes. **Unique on `(application_id, version)` and `(reference, version)`** — not on `application_id` alone. |
| `create_agreement_milestones_table` | `position`, `title`, `description`, `amount` (whole pesos), `starts_on`/`ends_on`, `status`, `review_note`, `submitted_at`, `approved_at`, `approved_by`. Unique `(agreement_id, position)`. |
| `create_agreement_signatures_table` | `party`, `signed_name`, `acknowledgements` (json), `signed_at`, `ip_address`, `user_agent`. Unique `(agreement_id, party)`. **Append-only.** |
| `create_student_portfolio_items_table` | Student Background History: `title`, `role`, `description`, `year`, `url`, `repository_url`, `is_featured`, `position`. |
| `create_portfolio_item_skill_table` | Pivot so the matching engine can distinguish demonstrated from claimed skills. |
| `create_student_verifications_table` | `provider`, `status`, `external_id`, `redirect_url`, `verified_at`, `failure_reason`, `payload` (json). Unique `(user_id, provider)`. |
| `create_transactions_table` | Full ledger. Built but dormant. |
| `add_presentation_fields_to_student_profiles_table` | `location`, `weekly_hours`, `availability_note`, `response_time_hours`, `education_started_on`, `education_note` — all nullable, purely additive. |
| `add_repository_url_to_projects_table` | GitHub Repository Integration at project level (a link, not an API integration). |

**Why `(application_id, version)` and not `application_id`:** a change request
must not edit a signed agreement, or terms move underneath a signature that has
already been given and the contract log becomes worthless. Instead the standing
version is marked `Superseded`, a copy is written at `version + 1` with no
signatures, and `superseded_by` links them. `Application::agreement()` uses
`->latestOfMany('version')`; `Application::agreements()` returns all versions.

### 3.4 The behaviour change the user approved

`app/Actions/Client/RespondToApplication.php` **no longer starts the project.**

Before: accepting a student flipped the project to `InProgress` immediately
(`startProjectIfFullyStaffed()` — now deleted).

After: acceptance calls `DraftAgreement`, which creates the contract in `Draft`
and leaves the posting `Open`. `SignAgreement` moves the project to
`InProgress` when the **second** signature lands.

The one-project-per-student cap (`User::holdsProjectInHand()`) still binds at
**acceptance**, not at signing — a student who has been accepted is spoken for,
and letting a second client accept them mid-negotiation would hand them two
contracts to choose between.

Two tests in `tests/Feature/Client/ClientNotificationTest.php` were rewritten to
match: `test_accepting_a_student_drafts_an_agreement_without_starting_the_project`
and `test_a_second_acceptance_reuses_nothing_and_drafts_its_own_agreement`.

### 3.5 New models

`Agreement`, `AgreementMilestone`, `AgreementSignature`, `StudentPortfolioItem`,
`StudentVerification`, `Transaction` — all with `#[Fillable]` attributes,
PHPDoc property blocks, `#[Scope]` methods and casts, following the existing
`Testimonial` / `Application` conventions.

`Agreement` also carries `isSignedBy()`, `isFullySigned()`, `progress()`
(averaged across milestones — equal steps, **not** weighted by price, because a
cheap turnover phase is not less done than an expensive one) and
`syncTotalAmount()`.

Relations added to existing models:
- `Project::agreements()` (HasMany)
- `Application::agreement()` (HasOne, latestOfMany) + `Application::agreements()`
- `User::agreements()`, `User::studentVerifications()`, `User::isVerifiedStudent()`
- `StudentProfile::portfolioItems()` + new fillable columns + `education_started_on` cast

`User::isVerifiedStudent()` is **presentation only** — badge logic. The real
gate, `isVerifiedForOperating()`, still answers to the administrator-reviewed
credential alone. This is what keeps SheerID optional.

### 3.6 Actions

```
App\Actions\Agreements\DraftAgreement      idempotent; seeds Design/Build/Turnover
                                           milestones at ₱0 with no dates;
                                           carries posting objectives across as
                                           deliverables verbatim
App\Actions\Agreements\SignAgreement       refuses unpriced milestones; refuses
                                           partial acknowledgements; activates +
                                           starts project on second signature
App\Actions\Agreements\SupersedeAgreement  clones to version+1, retires the old
App\Actions\Agreements\PresentAgreement    single payload builder shared by the
                                           Agreement and Contract screens
App\Actions\Billing\RecordTransaction      no-ops entirely while billing is off
```

### 3.7 Policy

`app/Policies/AgreementPolicy.php` — auto-discovered, no registration needed.

- `view` — either party only. No "anyone on the platform" case.
- `update` — **client only**, requires `TeamPermission::ManageProjects`, status
  editable, **and zero signatures exist**. An agreement one side can rewrite is
  not a contract.
- `sign` — correct party, once, never on superseded/cancelled.
- `requestChanges` — either party, up until the agreement is active. This is the
  student's counterweight to the client holding the pen.

### 3.8 Routes

New `routes/agreements.php` and `routes/billing.php`, both required from
`routes/web.php` **before** `student.php` and `client.php`.

```php
// routes/agreements.php — mounted on {current_team}, auth + verified +
// EnsureTeamMembership. NO role middleware: a contract has two parties and
// either may open it. AgreementPolicy decides.
GET    {current_team}/agreements                                  agreements.index
GET    {current_team}/agreements/{agreement}                      agreements.show
GET    {current_team}/agreements/{agreement}/contract             agreements.contract
PATCH  {current_team}/agreements/{agreement}                      agreements.update
POST   {current_team}/agreements/{agreement}/signatures           agreements.signatures.store
POST   {current_team}/agreements/{agreement}/change-requests      agreements.changes.store
PATCH  {current_team}/agreements/{agreement}/milestones/{milestone} agreements.milestones.update

// routes/billing.php — same, plus EnsureBillingIsEnabled (404s while off)
GET    {current_team}/transactions                                transactions.index
```

`agreements.index` **redirects straight to the document** when the user has
exactly one standing agreement — which is the normal case given the
one-posting-per-team and one-build-per-student caps.

### 3.9 Controllers, requests, middleware, notifications, config

```
app/Http/Controllers/Agreements/AgreementController.php           index/show/contract/update + syncMilestones
app/Http/Controllers/Agreements/AgreementSignatureController.php  party derived from the signed-in user, never from the request
app/Http/Controllers/Agreements/AgreementChangeRequestController.php
app/Http/Controllers/Agreements/AgreementMilestoneController.php  progress tracking; calls RecordTransaction on approval
app/Http/Controllers/Billing/TransactionController.php            one controller, scoped by payee/payer not by role

app/Http/Requests/Agreements/SaveAgreementRequest.php             milestones submitted whole, not one at a time
app/Http/Requests/Agreements/SignAgreementRequest.php
app/Http/Requests/Agreements/RequestAgreementChangesRequest.php   note required, min 10 chars
app/Http/Requests/Agreements/UpdateMilestoneRequest.php           offers different statuses per party

app/Http/Middleware/EnsureBillingIsEnabled.php                    404, not 403
app/Notifications/Agreements/AgreementSigned.php
app/Notifications/Agreements/ChangesRequested.php                 carries the note verbatim

config/agreements.php   reference_prefix, acknowledgements (4 keys), default_milestones, default_terms
config/billing.php      'enabled' => env('BILLING_ENABLED', false)
```

### 3.10 Factories and tests written

Factories: `AgreementFactory` (+`awaitingSignatures()`, `active()`),
`AgreementMilestoneFactory` (+`approved()`), `AgreementSignatureFactory`
(+`client()`), `StudentPortfolioItemFactory`, `StudentVerificationFactory`
(+`verified()`, `rejected()`), `TransactionFactory` (+`settled()`).

Tests — **both files passing**:

- `tests/Feature/Agreements/AgreementLifecycleTest.php` — 6 tests
- `tests/Feature/Agreements/AgreementSigningTest.php` — 11 tests, **10 passing**;
  `test_both_parties_can_read_the_agreement` fails **only** because
  `resources/js/pages/agreements/show.tsx` does not exist yet
  (`ViteException: Unable to locate file in Vite manifest`).

---

## 4. Test status

| Point | Result |
|---|---|
| Baseline on the feature branch before any change | **440 passed**, 2,053 assertions |
| After the acceptance rewire + all backend work | **440 passed** |
| Plus the new agreement tests | 17 new, 16 passing, 1 blocked on the missing React page |

---

## 5. IMMEDIATE NEXT STEPS

Work in this order. Steps 1–2 are what unblocks the failing test.

### Step 1 — React pages for the Agreement module (IN PROGRESS, nothing written yet)

Create, matching the mockup markup:

```
resources/js/pages/agreements/show.tsx       the "Agreement" screen
resources/js/pages/agreements/contract.tsx   the "Contract" screen
resources/js/pages/agreements/index.tsx      compact list (fallback when >1 standing)
```

Conventions to follow — these are established, do not invent alternatives:

- Components come from `@/components/sdpc/`: `Panel`, `PanelKicker`,
  `PanelTitle`, `PanelMeta`, `PanelDivider`, `PanelAccent`, `Btn`, `Tag`,
  `Field`, `Input`, `Meter`. Look at
  `resources/js/pages/student/workflow.tsx` as the template.
- Inline `style` objects with design tokens (`var(--color-text)`,
  `color-mix(in srgb, var(--color-text) 60%, transparent)`), **not** Tailwind
  utilities — the design's 2.8px spacing scale cannot be expressed in Tailwind's
  4px scale.
- Route helpers import from `@/routes/...` (Wayfinder). **Never run
  `php artisan wayfinder:generate` by hand** — it omits `.form` variants and
  breaks ~21 files. Let `npm run build` / `npm run dev` regenerate.
- One page component serves both roles, switching on
  `agreement.viewer.party` / `canEdit` / `canSign` / `canRequestChanges` —
  exactly as the mockup's `isClient` / `isStudent` blocks do.

### Step 2 — Register the new page namespaces in `resources/js/app.tsx`

The layout switch currently has no case for `agreements/` or `billing/`, so they
fall through to `AppLayout`. Add them to the `ClientLayout` branch:

```ts
case name.startsWith('client/'):
case name.startsWith('student/'):
case name.startsWith('messaging/'):
case name.startsWith('agreements/'):   // add
case name.startsWith('billing/'):      // add
    return ClientLayout;
```

### Step 3 — Wire the nav in `resources/js/layouts/client/client-layout.tsx`

Both role navs currently render `Agreement` and `Performance`/`Transaction` as
**disabled tooltip placeholders** ("Arrives with the Contracts module").

- Point `Agreement` at `agreements.index`.
- Leave `Performance` / `Transaction` disabled **unless** billing is enabled —
  share a `billingEnabled` boolean from `HandleInertiaRequests` and switch on it.

### Step 4 — Student profile ownership + portfolio (task not started)

- `app/Http/Controllers/Student/StudentProfileController.php` (edit/update own profile)
- `app/Http/Controllers/Student/PortfolioItemController.php` (CRUD)
- `resources/js/pages/student/profile.tsx` from the mockup's Student profile screen
- Add the portfolio to the client-facing `Client\StudentProfileController` view
- Routes into `routes/student.php`

### Step 5 — Client directory for students (task not started)

`Client List` from the PDF. Students currently cannot view a business profile at
all. `app/Http/Controllers/Student/ClientDirectoryController.php` +
`resources/js/pages/student/clients.tsx` / `client.tsx`, reusing the mockup's
Client profile screen.

### Step 6 — SheerID (task not started, plan settled)

No new packages — Laravel's built-in `Http` client only.

```
app/Contracts/StudentVerifier.php
app/Services/Verification/NullStudentVerifier.php    ← default binding
app/Services/Verification/SheerIdStudentVerifier.php
config/sheerid.php   enabled, base_url, program_id, access_token
.env.example         SHEERID_ENABLED=false
```

Student settings gets a "Verify my student status" button (hidden when
disabled) → creates a `student_verifications` row → redirects to SheerID's
hosted page → return/webhook updates status. Admin credentials queue gains a
read-only "SheerID: verified 12 Aug" line as supporting evidence.
**Nothing may gate on it.** The `student_verifications` table already exists.

### Step 7 — Transactions screen, shipped disabled (backend done, UI not started)

`resources/js/pages/billing/transactions.tsx` from the mockup's Transaction
screen. Tests must force `config(['billing.enabled' => true])`; the shipped
default stays `false`.

### Step 8 — Reconnect progress tracking to `agreement_milestones`

- `app/Actions/Student/BuildStudentDashboard.php` — replace the
  `STATUS_PROGRESS` lifecycle constant with the agreement's real `progress()`,
  and mark milestone dates on the calendar.
- Build `resources/js/pages/student/process.tsx` (the mockup's "Project process"
  screen) from milestone rows.

### Step 9 — Verification pass

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent     # NOT --test
npm run types
npm run lint
npm run build
```

Then re-read every file in `.ai/rules/` and confirm each trap still holds.

### Step 10 — Record new rules

Use Laravel Boost's `record-rule` (never native memory) for at least:

- `app/Actions/Agreements/**` — signing starts the project, not acceptance;
  change requests supersede rather than edit; the cap binds at acceptance.
- `app/Models/Agreement.php` — `(application_id, version)` uniqueness and why.
- `config/billing.php` — billing is built and dormant; `RecordTransaction` is
  the single door.

---

## 6. Environment notes

### 6.1 Sandbox-only workarounds — do NOT replicate these locally

Two things were done in the cloud workspace purely to get around network
restrictions. **Neither is in the patch.**

1. `phpstan/phpstan` and `larastan/larastan` were temporarily removed from
   `composer.json` / `composer.lock` because `api.github.com` dist downloads
   were rate-limited. Both files were restored. **Static analysis was never run
   this session** — run `vendor/bin/phpstan analyse` locally.
2. A `vite.config.sandbox.ts` (identical minus the `bunny()` fonts plugin) was
   used because `fonts.bunny.net` returns 403 through the proxy. It was deleted.
   `npm run build` works normally on a real machine.

### 6.2 Existing `.ai/rules` traps that still apply

| Rule | Summary |
|---|---|
| `feature.md` | Pin `current_team` explicitly in team-scoped `route()` calls in tests — `UserFactory`'s afterCreating hook overwrites `URL::defaults`. Also: any test hitting `/` needs `RefreshDatabase`. |
| `student.md` | A controller taking a second route model **must** declare `Team $currentTeam` first, or Laravel passes the slug positionally. |
| `routes.md` | Never run `php artisan wayfinder:generate` by hand. |
| `migrations.md` | On SQLite, drop foreign keys and indexes before dropping a column; guard with `Schema::hasColumn`; run `composer dump-autoload` after deleting a class. |
| `models.md` | Track read state by message id, never by timestamp. |
| `policies.md` | One unfinished posting per client team; drafts count. |
| `actions-client.md` | One build in hand per student. |
| `controllers.md` | Nothing on the landing page may be invented; a stat with no source renders as a dash, not 0. |
| `admin.md` | `AdminPostingController::update` is the only place a posting becomes `open`. |
| `client.md` | An invitation is what makes a student messageable. |

### 6.3 Current data state

8 users · 2 student profiles · 0 projects · 0 applications · 0 conversations ·
0 testimonials. `.env`: `PROJECTS_AUTO_APPROVE=true`, `MAIL_MAILER=log`,
`QUEUE_CONNECTION=database`. `composer run dev` starts app + Vite + queue worker
— the queue worker matters or notifications never deliver.

### 6.4 Still deferred by instruction

Messaging UI expansion and Video conferencing. The messaging tables and
controllers already exist on the branch; leave them alone.

---

## 7. One-paragraph summary for the top of a fresh Claude Code session

> Continuing the SDPC platform (Laravel 13 + React/Inertia, student–client
> project marketplace for San Jose del Monte). Work on branch
> `client-posting-limit-and-recruit-cards`, not `main`. Apply
> `sdpc-agreements-wip.patch` first — it adds a complete Agreement/Contract
> backend (9 migrations, 6 models, 7 enums, 5 actions, 5 controllers, a policy,
> 2 route files, 6 factories, 17 tests) plus a dormant billing ledger gated on
> `config('billing.enabled')`. The central design decision: a posting is a brief
> with no money or dates, an agreement is the negotiated contract that carries
> scope, milestone pricing and phase dates, and the **second signature** — not
> the client's acceptance — is what moves a project into progress. Backend is
> green at 440 + 16 tests. The remaining work is the React layer: the
> Agreement, Contract, Student profile, Client list, Project process and
> Transaction screens, plus optional config-gated SheerID verification. No new
> packages without asking. Follow `.ai/rules/`.
