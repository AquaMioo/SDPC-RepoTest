---
paths:
  - 'resources/css/**'
---

# Css

## Nocturne owns --color-accent, so shadcn's accent utilities live under --color-ui-accent
nocturne.css redefines `--color-accent` on `[data-mod]`, and `[data-mod]` is set on `<html>` for every non-admin page. That is the same variable Tailwind generates `bg-accent` from, so `hover:bg-accent` painted the solid brand olive (#64764f) instead of the pale #dfe8d2 tint the pair was drawn as — and a ghost button's icon (`--accent-foreground`, #2f3928) disappeared into it. Every shadcn ghost button, dropdown item, select item and toggle was affected; QA found it as a green blob where the teams-page pencil should be.

The shadcn hover pair is therefore named `--color-ui-accent` / `--color-ui-accent-foreground` in app.css's `@theme`, and the utilities are `bg-ui-accent` / `text-ui-accent-foreground`. Do not reintroduce a `--color-accent` token in `@theme`: nocturne wins the cascade and the utility silently resolves to the brand colour.

`--color-neutral-100..900` collides the same way — nocturne inverts the ramp under `[data-mod="user"]`, so `text-neutral-900` is light there, not dark. Anything still using Tailwind's neutral scale (app-header.tsx, nav-footer.tsx) is rendering with an inverted palette.

## A hover that shifts to --color-accent-300 is invisible on the user palette
Under `[data-mod="user"]` — which app.blade.php stamps on every non-admin page — the accent ramp runs the opposite way to the dark theme's: 100 is the darkest olive and `--color-accent-300` is `#64764f`, the exact value of `--color-accent`. So nocturne.css's generic `a:not([data-slot='button']):hover { color: var(--color-accent-300) }` resolves to the colour the link already had, and every plain link on the student and client sides has no hover at all.

For a link that must react on both palettes, lean on the page's own foreground instead: `color-mix(in srgb, var(--color-accent) 70%, var(--color-text))` brightens the green on the dark ground and deepens the olive on the light one from one declaration. `a[data-legal]` (the sign-up consent links) is the worked example.

Related: the `--color-neutral-*` ramp inverts the same way under `[data-mod="user"]`.
