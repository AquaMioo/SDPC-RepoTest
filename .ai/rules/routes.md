---
paths:
  - 'resources/js/routes/**'
---

# Routes

## Never run php artisan wayfinder:generate by hand
The Artisan command writes route helpers WITHOUT the `.form` variants, which instantly breaks ~21 files (auth, settings, teams, admin, two-factor) with "Property 'form' does not exist". The @laravel/vite-plugin-wayfinder in vite.config is the source of truth and emits them correctly. If you hit a wave of `.form` type errors you did not cause, that is what happened — run `npm run build` (or let `npm run dev` regenerate) to restore, and do not "fix" the call sites.

## Always run wayfinder:generate with --with-form
vite.config.ts configures wayfinder({ formVariants: true }), so the plugin generates a .form property on every route helper and roughly thirty screens use it. `php artisan wayfinder:generate` on its own does NOT default to that: it regenerates without the variants and `npm run types:check` then fails with "Property 'form' does not exist" across auth, settings, teams and the student profile dialogs — files you never touched.

Run `php artisan wayfinder:generate --with-form`, or just let vite regenerate them. resources/js/routes and resources/js/actions are gitignored, so git status will not warn you that you have flattened them.
