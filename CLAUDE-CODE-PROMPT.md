Continue development of the SDPC platform (Student Developer Project Connection) — a Laravel 13 + React/Inertia web app connecting tertiary students in San Jose del Monte, Bulacan with local business clients. Repo root: C:\Users\irven\sdpc-platform

## Before anything else — two setup steps

**1. Switch branches.** My checkout is on `main`, which does NOT contain the Student Module, matching engine, messaging, testimonials or the admin posting queue. All of that is on `client-posting-limit-and-recruit-cards`:

```
git fetch origin
git checkout client-posting-limit-and-recruit-cards
```

**2. Apply the patch.** The previous session's work exists only as a patch file at the repo root — `sdpc-agreements-wip.patch` (48 files: 43 new, 5 modified). It is not committed anywhere:

```
git apply --3way sdpc-agreements-wip.patch
composer install && npm install
php artisan migrate
php artisan test --compact
```

Expected after applying: 440 baseline tests pass, plus 16 of 17 new agreement tests. The single failure is `test_both_parties_can_read_the_agreement`, which fails only because `resources/js/pages/agreements/show.tsx` doesn't exist yet — that's the first task below.

**Read `SDPC-HANDOFF.md` at the repo root** for the full inventory, reasoning and the mockup/PDF references. This prompt is the short version.

## Standing rules for this project

- Laravel 13 + React/Inertia only. **No new composer or npm packages** without asking me first.
- Follow `.ai/rules/` — open `.ai/rules/index.md`, read every rule file whose globs cover the paths you're touching, and `grep -rin 'keyword' .ai/rules` before writing code. There are real traps in there (Wayfinder, SQLite column drops, `current_team` in tests, `Team $currentTeam` parameter ordering).
- Keep the UI as close as possible to the HTML mockup. Do not invent new components. Client UI and Student UI stay cleanly separated by account type.
- Every change gets a test. Run `php artisan test --compact` with a filter, then `vendor/bin/pint --dirty --format agent`, `npm run types`, `npm run lint`, `npm run build`.
- Messaging and Video Conferencing are **deferred** — the tables and controllers exist on the branch, leave them alone.
- Plan before you code. Show me the plan first for anything non-trivial.

## What was already decided — don't re-litigate these

**A posting is a brief; an agreement is the contract.** An earlier session stripped budget, all four dates, team size, experience level and milestones off `projects`, which left the dashboard progress ring and calendar with no data source. Rather than putting those columns back on the posting, they now live on the new `agreements` / `agreement_milestones` tables — scope, milestone pricing and phase dates are negotiated after the client accepts a student. This matches the mockup's Agreement screen exactly.

**Signing starts the project, not acceptance.** `RespondToApplication` used to flip a project to `in_progress` the moment a client accepted. Now acceptance calls `DraftAgreement` and leaves the posting open; `SignAgreement` moves it to `in_progress` when the **second** signature lands. The one-build-per-student cap still binds at acceptance, so nobody gets double-booked mid-negotiation. (This follows the capstone PDF: the Terms and Agreements Form appears "before collaboration begins.")

**Change requests supersede, they don't edit.** If terms can move after somebody signs, the contract log is worthless. So `agreements` is unique on `(application_id, version)`, the old row is marked `superseded` with `superseded_by` pointing forward, and `agreement_signatures` is append-only.

**Billing is built and switched off.** The `transactions` table, model, `RecordTransaction` action, controller and `EnsureBillingIsEnabled` middleware all exist. `config('billing.enabled')` defaults to false, the routes 404, and `RecordTransaction` no-ops. I want it ready to switch on later — do not activate it, and do not add a payment gateway package.

**SheerID must stay optional.** Nothing may gate on it. `User::isVerifiedStudent()` is badge presentation only; `isVerifiedForOperating()` (the real gate) still answers to the administrator-reviewed credential alone.

## Work to do, in order

**1. Agreement React pages** (unblocks the failing test)

Create `resources/js/pages/agreements/show.tsx`, `contract.tsx` and `index.tsx` from the mockup's Agreement and Contract screens.

- Use `@/components/sdpc/` primitives: `Panel`, `PanelKicker`, `PanelTitle`, `PanelMeta`, `PanelDivider`, `PanelAccent`, `Btn`, `Tag`, `Field`, `Input`, `Meter`. Use `resources/js/pages/student/workflow.tsx` as the structural template.
- Inline `style` objects with design tokens (`var(--color-text)`, `color-mix(...)`), not Tailwind utilities — the design's 2.8px spacing scale can't be expressed on Tailwind's 4px scale.
- Route helpers import from `@/routes/...` (Wayfinder). **Never run `php artisan wayfinder:generate` by hand** — it omits `.form` variants and breaks ~21 files.
- One page component serves both roles, switching on `agreement.viewer.party` / `canEdit` / `canSign` / `canRequestChanges` — the same way the mockup's `isClient` / `isStudent` blocks work.

The `PresentAgreement` action already builds the whole payload; read it first.

**2. Register the new namespaces** in `resources/js/app.tsx` — add `agreements/` and `billing/` to the `ClientLayout` branch of the layout switch.

**3. Wire the nav** in `resources/js/layouts/client/client-layout.tsx`. Both role navs currently show `Agreement` and `Performance`/`Transaction` as disabled tooltip placeholders. Point `Agreement` at `agreements.index`. Leave `Performance`/`Transaction` disabled unless billing is enabled — share a `billingEnabled` boolean from `HandleInertiaRequests` and switch on it.

**4. Student profile ownership + portfolio.** Students currently cannot edit their own profile at all. Build `Student\StudentProfileController` (edit/update), `Student\PortfolioItemController` (CRUD), and `resources/js/pages/student/profile.tsx` from the mockup's Student profile screen. Surface the portfolio on the client-facing student profile view too. The `student_portfolio_items` table and the new `student_profiles` columns (`location`, `weekly_hours`, `availability_note`, `response_time_hours`, `education_started_on`, `education_note`) already exist.

**5. Client directory for students** — "Client List" from the PDF. Students cannot view a business profile at all right now. Build `Student\ClientDirectoryController` plus `resources/js/pages/student/clients.tsx` and `client.tsx`, reusing the mockup's Client profile screen.

**6. SheerID verification, config-gated.** No new packages — Laravel's built-in `Http` client only. Create `app/Contracts/StudentVerifier.php`, `NullStudentVerifier` (the default binding), `SheerIdStudentVerifier`, and `config/sheerid.php` with `enabled`, `base_url`, `program_id`, `access_token`. Add `SHEERID_ENABLED=false` to `.env.example`. Student settings gets a "Verify my student status" button, hidden when disabled. The admin credentials queue gains a read-only "SheerID: verified 12 Aug" line as supporting evidence. The `student_verifications` table already exists. I have no credentials yet, so the null driver must be the shipped default.

**7. Transactions screen, shipped disabled.** Build `resources/js/pages/billing/transactions.tsx` from the mockup's Transaction screen. Tests force `config(['billing.enabled' => true])`; the shipped default stays false.

**8. Reconnect progress tracking.** Replace the `STATUS_PROGRESS` lifecycle constant in `app/Actions/Student/BuildStudentDashboard.php` with the agreement's real `progress()`, mark milestone dates on the dashboard calendar, and build `resources/js/pages/student/process.tsx` (the mockup's "Project process" screen) from milestone rows.

**9. Verification pass** — full suite, pint, types, lint, build; then re-read every `.ai/rules/` file and confirm each trap still holds.

**10. Record new rules** with Laravel Boost's `record-rule` (never native memory) for: the acceptance/signing split, the `(application_id, version)` uniqueness and why, and billing being built-but-dormant with `RecordTransaction` as the single door.

## One thing to note

Static analysis was never run in the previous session — `phpstan`/`larastan` couldn't be installed in that sandbox. Please run `vendor/bin/phpstan analyse` early and fix anything it finds in the new code.
