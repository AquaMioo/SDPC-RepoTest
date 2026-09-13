---
paths:
  - 'resources/js/routes/**'
---

# Routes

## Never run wayfinder:generate bare — it strips every .form variant
This has now been recorded three separate times by three people who each hit it fresh, which is the best evidence there is that it keeps happening. Consolidated here.

vite.config.ts configures `wayfinder({ formVariants: true })`, so the generated helpers carry a `.form` property that roughly thirty screens rely on — create-team-modal, manage-two-factor, profile-dialogs, admin/login, auth, settings, teams, two-factor.

`php artisan wayfinder:generate` on its own does NOT default to that. It regenerates without the variants and silently strips `.form` from every helper, and `npm run types:check` then fails with ~30 TS2339 "Property 'form' does not exist" errors in files you never touched. It reads like a broken dependency rather than something you just did to yourself.

Run `php artisan wayfinder:generate --with-form`, or let vite regenerate them (`npm run dev` / `npm run build`). Do NOT "fix" the call sites.

resources/js/routes and resources/js/actions are gitignored, so git status will never warn you that you have flattened them — tsc is the only signal.
