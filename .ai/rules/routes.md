---
paths:
  - 'resources/js/routes/**'
---

# Routes

## Never run php artisan wayfinder:generate by hand
The Artisan command writes route helpers WITHOUT the `.form` variants, which instantly breaks ~21 files (auth, settings, teams, admin, two-factor) with "Property 'form' does not exist". The @laravel/vite-plugin-wayfinder in vite.config is the source of truth and emits them correctly. If you hit a wave of `.form` type errors you did not cause, that is what happened — run `npm run build` (or let `npm run dev` regenerate) to restore, and do not "fix" the call sites.
