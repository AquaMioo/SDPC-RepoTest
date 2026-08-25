---
paths:
  - 'resources/css/**'
---

# Css

## Nocturne owns --color-accent, so shadcn's accent utilities live under --color-ui-accent
nocturne.css redefines `--color-accent` on `[data-mod]`, and `[data-mod]` is set on `<html>` for every non-admin page. That is the same variable Tailwind generates `bg-accent` from, so `hover:bg-accent` painted the solid brand olive (#64764f) instead of the pale #dfe8d2 tint the pair was drawn as — and a ghost button's icon (`--accent-foreground`, #2f3928) disappeared into it. Every shadcn ghost button, dropdown item, select item and toggle was affected; QA found it as a green blob where the teams-page pencil should be.

The shadcn hover pair is therefore named `--color-ui-accent` / `--color-ui-accent-foreground` in app.css's `@theme`, and the utilities are `bg-ui-accent` / `text-ui-accent-foreground`. Do not reintroduce a `--color-accent` token in `@theme`: nocturne wins the cascade and the utility silently resolves to the brand colour.

`--color-neutral-100..900` collides the same way — nocturne inverts the ramp under `[data-mod="user"]`, so `text-neutral-900` is light there, not dark. Anything still using Tailwind's neutral scale (app-header.tsx, nav-footer.tsx) is rendering with an inverted palette.
